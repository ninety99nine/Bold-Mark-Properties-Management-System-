<?php

namespace App\Services;

use App\Helpers\MessageTemplateDefaults;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Http\Resources\MessageTemplateResource;
use App\Http\Resources\MessageTemplateResources;

class MessageTemplateService extends BaseService
{
    /**
     * List the organization's message templates in canonical order, lazily
     * seeding any that do not yet exist from the defaults.
     *
     * @param Organization $organization
     * @return MessageTemplateResources
     */
    public function showTemplates(Organization $organization): MessageTemplateResources
    {
        $this->syncDefaults($organization);

        $order = array_column(MessageTemplateDefaults::all(), 'key');

        $templates = MessageTemplate::where('organization_id', $organization->id)
            ->get()
            ->sortBy(fn (MessageTemplate $t) => array_search($t->key, $order))
            ->values();

        return new MessageTemplateResources($templates);
    }

    /**
     * Update a single template's subject/body/terms.
     *
     * @param MessageTemplate $template
     * @param array           $data
     * @return array
     */
    public function updateTemplate(MessageTemplate $template, array $data): array
    {
        $template->update(collect($data)
            ->only(['subject', 'body', 'terms'])
            ->toArray());

        return $this->showUpdatedResource($template);
    }

    /**
     * Restore a template to its canonical default content.
     *
     * @param MessageTemplate $template
     * @return array
     */
    public function resetTemplate(MessageTemplate $template): array
    {
        $default = MessageTemplateDefaults::find($template->key);

        if ($default) {
            $template->update([
                'subject' => $default['subject'],
                'body'    => $default['body'],
                'terms'   => $default['terms'],
            ]);
        }

        return $this->showUpdatedResource($template);
    }

    /**
     * Wrap a single template in its resource.
     *
     * @param MessageTemplate $template
     * @return MessageTemplateResource
     */
    public function showTemplate(MessageTemplate $template): MessageTemplateResource
    {
        return new MessageTemplateResource($template);
    }

    /**
     * Ensure every default template exists for the organization. Only creates
     * missing rows — existing (possibly customised) templates are untouched.
     *
     * @param Organization $organization
     * @return void
     */
    public function syncDefaults(Organization $organization): void
    {
        $existingKeys = MessageTemplate::where('organization_id', $organization->id)
            ->pluck('key')
            ->all();

        foreach (MessageTemplateDefaults::all() as $default) {
            if (in_array($default['key'], $existingKeys, true)) {
                continue;
            }

            MessageTemplate::create([
                'organization_id' => $organization->id,
                'key'             => $default['key'],
                'name'            => $default['name'],
                'type'            => $default['type'],
                'subject'         => $default['subject'],
                'body'            => $default['body'],
                'terms'           => $default['terms'],
            ]);
        }
    }
}
