<?php

namespace App\Services;

use App\Enums\CommunicationType;
use App\Enums\CommunityMemberType;
use App\Enums\RecipientGroup;
use App\Models\Communication;
use App\Models\CommunicationRecipient;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Owner;
use App\Models\Unit;
use App\Http\Resources\CommunicationResource;
use App\Http\Resources\CommunicationResources;
use App\Http\Resources\CommunityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Resend\Laravel\Facades\Resend;

class CommunicationService extends BaseService
{
    /**
     * List sent communications (Archive tab), newest first. Scoped to a single
     * community when provided, otherwise the whole organization (global page).
     *
     * @param Organization  $organization
     * @param Community|null $community
     * @return CommunicationResources
     */
    public function showCommunications(Organization $organization, ?Community $community = null): CommunicationResources
    {
        $query = Communication::where('organization_id', $organization->id)
            ->with(['community', 'recipients'])
            ->latest();

        if ($community) {
            $query->where('community_id', $community->id);
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Compose + send a communication to the selected recipient groups, logging
     * the batch and every recipient. Mirrors UnitService::sendCommunication for
     * the Resend call (suppressed locally + in tests). SMS is persisted only
     * (Twilio dispatch deferred).
     *
     * @param Organization $organization
     * @param Community    $community
     * @param array        $data
     * @param array        $files
     * @return array
     */
    public function sendCommunication(Organization $organization, Community $community, array $data, array $files = []): array
    {
        $user = Auth::user();

        $groups     = $data['recipient_groups'] ?? [];
        $recipients = $this->resolveRecipients($community, $groups);

        $type    = $data['type'] ?? CommunicationType::MAIL->value;
        $subject = $data['subject'] ?? null;
        $body    = $data['body'] ?? '';

        $fromEmail = $data['from_email'] ?? ($organization->outgoing_email ?: config('mail.from.address'));
        $fromName  = $organization->company_name ?: config('mail.from.name');

        $bccList = collect(explode(',', (string) ($data['bcc'] ?? '')))
            ->map(fn ($e) => trim($e))->filter()->values()->all();

        // Prepare attachments once (base64) for reuse per recipient.
        $attachments     = [];
        $attachmentNames = [];
        foreach ($files as $file) {
            $attachmentNames[] = $file->getClientOriginalName();
            $attachments[] = [
                'filename' => $file->getClientOriginalName(),
                'content'  => base64_encode(file_get_contents($file->getRealPath())),
            ];
        }

        $communication = Communication::create([
            'organization_id'  => $organization->id,
            'community_id'     => $community->id,
            'from_name'        => $fromName,
            'from_email'       => $fromEmail,
            'subject'          => $subject,
            'body'             => $body,
            'bcc'              => ! empty($bccList) ? implode(', ', $bccList) : null,
            'type'             => $type,
            'status'           => 'queued',
            'sent_by_name'     => $user?->name ?? config('mail.from.name'),
            'sent_by_user_id'  => $user?->id,
            'recipient_groups' => array_values($groups),
            'attachment_names' => ! empty($attachmentNames) ? $attachmentNames : null,
            'recipient_count'  => $recipients->count(),
        ]);

        $isMail       = $type === CommunicationType::MAIL->value;
        $renderedBody = $isMail ? $this->wrapWithBranding($body, $community, $organization) : $body;
        $suppress     = app()->isLocal() || app()->runningUnitTests();

        $sentCount  = 0;
        $errorCount = 0;
        $first      = true;

        foreach ($recipients as $recipient) {
            $resendId = null;
            $status   = 'queued';
            $error    = null;

            if ($isMail) {
                if ($suppress) {
                    $status = 'sent';
                    Log::info("[local] Communication email suppressed — would send to {$recipient['email']}", [
                        'community' => $community->name, 'subject' => $subject,
                    ]);
                } else {
                    try {
                        $payload = [
                            'from'    => "{$fromName} <{$fromEmail}>",
                            'to'      => [$recipient['email']],
                            'subject' => $subject,
                            'html'    => $renderedBody,
                        ];
                        if ($first && ! empty($bccList)) $payload['bcc']         = $bccList;
                        if (! empty($attachments))       $payload['attachments'] = $attachments;

                        $response = Resend::emails()->send($payload);
                        $resendId = $response->id ?? null;
                        $status   = 'sent';
                    } catch (\Throwable $e) {
                        $status = 'failed';
                        $error  = $e->getMessage();
                    }
                }
            }

            $status === 'failed' ? $errorCount++ : $sentCount++;

            CommunicationRecipient::create([
                'communication_id' => $communication->id,
                'recipient_name'   => $recipient['name'] ?? null,
                'recipient_email'  => $recipient['email'],
                'status'           => $status,
                'error'            => $error,
                'resend_email_id'  => $resendId,
                'view_token'       => (string) Str::uuid(),
            ]);

            $first = false;
        }

        $communication->update([
            'sent_count'  => $sentCount,
            'error_count' => $errorCount,
            'status'      => $this->resolveBatchStatus($recipients->count(), $sentCount, $errorCount),
        ]);

        $communication->load(['community', 'recipients']);

        return [
            'data'    => new CommunicationResource($communication),
            'message' => 'Communication sent.',
        ];
    }

    /**
     * Re-send an existing communication to its original recipients.
     *
     * @param Communication $communication
     * @return array
     */
    public function resendCommunication(Communication $communication): array
    {
        $organization = $communication->organization;
        $community    = $communication->community;
        $suppress     = app()->isLocal() || app()->runningUnitTests();
        $isMail       = $communication->type === CommunicationType::MAIL;
        $renderedBody = $isMail ? $this->wrapWithBranding($communication->body ?? '', $community, $organization) : $communication->body;

        foreach ($communication->recipients as $recipient) {
            if (! $isMail) {
                continue;
            }

            if ($suppress) {
                $recipient->update(['status' => 'sent', 'error' => null]);
                continue;
            }

            try {
                $response = Resend::emails()->send([
                    'from'    => "{$communication->from_name} <{$communication->from_email}>",
                    'to'      => [$recipient->recipient_email],
                    'subject' => $communication->subject,
                    'html'    => $renderedBody,
                ]);
                $recipient->update([
                    'status'          => 'sent',
                    'error'           => null,
                    'resend_email_id' => $response->id ?? null,
                ]);
            } catch (\Throwable $e) {
                $recipient->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        $communication->refresh()->load(['community', 'recipients']);
        $sent  = $communication->recipients->where('status', '!=', 'failed')->count();
        $error = $communication->recipients->where('status', 'failed')->count();
        $communication->update([
            'sent_count'  => $sent,
            'error_count' => $error,
            'status'      => $this->resolveBatchStatus($communication->recipients->count(), $sent, $error),
        ]);

        return [
            'data'    => new CommunicationResource($communication),
            'message' => 'Communication resent.',
        ];
    }

    /**
     * Return the community's email branding settings (Communicate → Settings).
     *
     * @param Community $community
     * @return CommunityResource
     */
    public function showEmailSettings(Community $community): CommunityResource
    {
        return new CommunityResource($community);
    }

    /**
     * Update the community's email logo/header/footer images (upload + removal).
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function updateEmailSettings(Community $community, array $data): array
    {
        $updateData = [];

        $uploads = [
            'email_logo'   => 'email_logo_url',
            'email_header' => 'email_header_url',
            'email_footer' => 'email_footer_url',
        ];
        foreach ($uploads as $field => $column) {
            $file = $this->request->file($field);
            if ($file instanceof UploadedFile) {
                $this->deleteStoredImage($community->{$column});
                $path = $file->store("community-email/{$community->id}", 'public');
                $updateData[$column] = Storage::disk('public')->url($path);
            }
        }

        $removals = [
            'remove_email_logo'   => 'email_logo_url',
            'remove_email_header' => 'email_header_url',
            'remove_email_footer' => 'email_footer_url',
        ];
        foreach ($removals as $flag => $column) {
            if ($this->request->boolean($flag)) {
                $this->deleteStoredImage($community->{$column});
                $updateData[$column] = null;
            }
        }

        $community->update($updateData);

        return [
            'data'    => new CommunityResource($community),
            'message' => 'Communication settings updated.',
        ];
    }

    /**
     * Delete a previously-stored public image given its full URL.
     *
     * @param string|null $url
     * @return void
     */
    private function deleteStoredImage(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = str_replace(Storage::disk('public')->url(''), '', $url);
        Storage::disk('public')->delete($path);
    }

    /**
     * Resolve the flat, de-duplicated recipient list for the selected groups.
     *
     * @param Community $community
     * @param array<string> $groups
     * @return \Illuminate\Support\Collection<int, array{name: ?string, email: string}>
     */
    public function resolveRecipients(Community $community, array $groups): \Illuminate\Support\Collection
    {
        $recipients = collect();

        foreach ($groups as $group) {
            match ($group) {
                RecipientGroup::OWNERS->value => $this->addOwners($recipients, $community),
                RecipientGroup::DIRECTORS->value => $this->addMembers($recipients, $community, true),
                RecipientGroup::COMPLEX_MANAGERS->value => $this->addMembers($recipients, $community, false),
                RecipientGroup::OCCUPANTS->value => $this->addOccupants($recipients, $community),
                RecipientGroup::RENTAL_AGENTS->value => $this->addUnitEmails($recipients, $community, 'rental_agent_email', 'Rental Agent'),
                RecipientGroup::ATTORNEYS->value => $this->addUnitEmails($recipients, $community, 'attorney_email', 'Attorney'),
                RecipientGroup::BONDHOLDERS->value => $this->addUnitEmails($recipients, $community, 'bondholder_email', 'Bondholder'),
                default => null,
            };
        }

        return $recipients
            ->filter(fn ($r) => ! empty($r['email']) && filter_var($r['email'], FILTER_VALIDATE_EMAIL))
            ->unique(fn ($r) => strtolower($r['email']))
            ->values();
    }

    /**
     * Wrap the message body with the community/organization email header and
     * footer images (community override → organization default).
     *
     * @param string        $bodyHtml
     * @param Community|null $community
     * @param Organization  $organization
     * @return string
     */
    public function wrapWithBranding(string $bodyHtml, ?Community $community, Organization $organization): string
    {
        $header = $community?->email_header_url ?: $organization->email_header_url;
        $footer = $community?->email_footer_url ?: $organization->email_footer_url;

        $html = '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#2b2b2b;font-size:14px;line-height:1.5;">';

        if ($header) {
            $html .= '<img src="' . e($header) . '" alt="" style="width:100%;max-width:600px;display:block;" />';
        }

        $html .= '<div style="padding:20px 4px;">' . $bodyHtml . '</div>';

        if ($footer) {
            $html .= '<img src="' . e($footer) . '" alt="" style="width:100%;max-width:600px;display:block;" />';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Aggregate batch status from per-recipient outcomes.
     *
     * @param int $total
     * @param int $sent
     * @param int $error
     * @return string
     */
    private function resolveBatchStatus(int $total, int $sent, int $error): string
    {
        if ($total === 0) {
            return 'sent';
        }

        if ($error === 0) {
            return 'sent';
        }

        return $sent > 0 ? 'partial' : 'failed';
    }

    /**
     * @param \Illuminate\Support\Collection $recipients
     * @param Community $community
     * @return void
     */
    private function addOwners(\Illuminate\Support\Collection $recipients, Community $community): void
    {
        Owner::where('organization_id', $community->organization_id)
            ->forCommunity($community->id)
            ->active()
            ->get(['full_name', 'email', 'secondary_emails'])
            ->each(function (Owner $owner) use ($recipients) {
                if ($owner->email) {
                    $recipients->push(['name' => $owner->full_name, 'email' => $owner->email]);
                }
                foreach ((array) $owner->secondary_emails as $secondary) {
                    if ($secondary) {
                        $recipients->push(['name' => $owner->full_name, 'email' => $secondary]);
                    }
                }
            });
    }

    /**
     * @param \Illuminate\Support\Collection $recipients
     * @param Community $community
     * @param bool $directors  true = directors/trustees, false = complex managers
     * @return void
     */
    private function addMembers(\Illuminate\Support\Collection $recipients, Community $community, bool $directors): void
    {
        $query = CommunityMember::where('community_id', $community->id);

        if ($directors) {
            $query->where(fn (Builder $q) => $q
                ->where('user_type', CommunityMemberType::DIRECTOR_TRUSTEE->value)
                ->orWhere('is_director_trustee', true));
        } else {
            $query->where('user_type', CommunityMemberType::COMPLEX_MANAGER->value);
        }

        $query->get(['name', 'email'])->each(function (CommunityMember $member) use ($recipients) {
            if ($member->email) {
                $recipients->push(['name' => $member->name, 'email' => $member->email]);
            }
        });
    }

    /**
     * @param \Illuminate\Support\Collection $recipients
     * @param Community $community
     * @return void
     */
    private function addOccupants(\Illuminate\Support\Collection $recipients, Community $community): void
    {
        Occupant::whereHas('unit', fn (Builder $q) => $q->where('community_id', $community->id))
            ->where('is_active', true)
            ->get(['full_name', 'email', 'secondary_emails'])
            ->each(function (Occupant $occupant) use ($recipients) {
                if ($occupant->email) {
                    $recipients->push(['name' => $occupant->full_name, 'email' => $occupant->email]);
                }
                foreach ((array) $occupant->secondary_emails as $secondary) {
                    if ($secondary) {
                        $recipients->push(['name' => $occupant->full_name, 'email' => $secondary]);
                    }
                }
            });
    }

    /**
     * @param \Illuminate\Support\Collection $recipients
     * @param Community $community
     * @param string $column
     * @param string $label
     * @return void
     */
    private function addUnitEmails(\Illuminate\Support\Collection $recipients, Community $community, string $column, string $label): void
    {
        Unit::where('community_id', $community->id)
            ->whereNotNull($column)
            ->get(['unit_number', $column])
            ->each(function (Unit $unit) use ($recipients, $column, $label) {
                if ($unit->{$column}) {
                    $recipients->push([
                        'name'  => "{$label} (Unit {$unit->unit_number})",
                        'email' => $unit->{$column},
                    ]);
                }
            });
    }
}
