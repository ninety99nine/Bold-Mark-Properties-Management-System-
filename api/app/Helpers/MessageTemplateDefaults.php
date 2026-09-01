<?php

namespace App\Helpers;

use App\Enums\MessageTemplateType;

class MessageTemplateDefaults
{
    /**
     * Canonical default content for every message template, mirroring the
     * WeConnectU "Message Setup" defaults (rebranded for Bold Mark — structure
     * and wording preserved). Keyed by template key and preserving display
     * order. Bodies are stored as HTML for the rich-text editors; the placeholder
     * tokens (e.g. [Customer Name], [Balance]) are resolved at send time.
     *
     * @return array<int, array{key: string, name: string, type: string, subject: ?string, body: string, terms: ?string}>
     */
    public static function all(): array
    {
        return [
            [
                'key'     => 'first_notice',
                'name'    => '1st Notice',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'ARREARS NOTICE',
                'body'    => "<p><strong><u>FRIENDLY REMINDER</u></strong></p>"
                    . "<p>[Customer Name]</p>"
                    . "<p>Dear [Customer Name],</p>"
                    . "<p>[Community Name]</p>"
                    . "<p><u>Re: [Community Name] - ARREAR LEVIES</u></p>"
                    . "<p>According to our records, your levies are in arrears in the amount of R[Balance]. Levies are due and payable monthly in advance on the 1st day of each month.</p>"
                    . "<p>Please take note, interest will be charged at [Interest Percent]% [Interest Period] on your outstanding balance after the 7th of each month.</p>"
                    . "<p>Please use your customer code [Customer Code] as reference when payment is made.</p>"
                    . "<p>If payment has been made, kindly ignore, and please advise us immediately of remittance details.</p>"
                    . "<p>Your levy account will be debited with an amount of R[Fine Amount], inclusive of VAT for the cost of this notice.</p>"
                    . "<p>Should you have any query in this regard, please do not hesitate to contact us.</p>"
                    . "<p>Thanking you in anticipation for your urgent attention to this matter.</p>"
                    . "<p>Yours faithfully,</p>"
                    . "<p>Management</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'second_notice',
                'name'    => '2nd Notice',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'ARREARS NOTICE',
                'body'    => "<p><strong><u>2ND REMINDER LETTER</u></strong></p>"
                    . "<p>Dear [Customer Name],</p>"
                    . "<p>Re: [Community Name] - ARREAR LEVIES</p>"
                    . "<p><u>Our previous reminder letter refers.</u></p>"
                    . "<p>According to our records, your levies remain in arrears in the amount of R[Balance]. Levies are due and payable monthly in advance on the 1st day of each month.</p>"
                    . "<p>If payment has been made, kindly ignore, and please advise us immediately of remittance details.</p>"
                    . "<p>Your levy account will be debited with an amount of R[Fine Amount], inclusive of VAT for the cost of this notice.</p>"
                    . "<p>Should you have any query in this regard, please do not hesitate to contact us.<br>Thanking you in anticipation for your urgent attention to this matter.</p>"
                    . "<p>Yours faithfully,</p>"
                    . "<p>Management</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'lod_hoa',
                'name'    => 'Letter of Demand HOA',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'LETTER OF DEMAND',
                'body'    => "<p><strong>ATT: [Customer Name]</strong></p>"
                    . "<p>RE: [Community Name] / YOURSELF</p>"
                    . "<p>We, the Managing Agent of <strong>[Community Name]</strong>, have been instructed to confirm the following:</p>"
                    . "<ol>"
                    . "<li>You are indebted to <strong>[Community Name]</strong> in the amount of R[Balance] for arrear levies, services and charges.</li>"
                    . "<li>The amount is [Days Label].</li>"
                    . "<li>Interest will be charged at [Interest Percent]% [Interest Period].</li>"
                    . "<li>The letter serves as a demand to yourself to pay the aforesaid amount, plus the cost of the letter of demand in the amount of <strong>R[Fine Amount]</strong> within 14 (fourteen) days as from date hereof, failure of which further legal actions will be taken against yourself for payment of the said amounts, together with further legal costs and interest thereon.</li>"
                    . "</ol>"
                    . "<p>Regards,<br>Management</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'lod_body_corporate',
                'name'    => 'Letter of Demand Body Corporate',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'FINAL DEMAND',
                'body'    => "<p><strong><u>NOTICE IN TERMS OF PRESCRIBED MANAGEMENT RULE 25(2) OF THE SECTIONAL TITLE SCHEME MANAGEMENT ACT</u></strong></p>"
                    . "<p><strong>ATT: [Customer Name]</strong></p>"
                    . "<p>RE: [Community Name] Body Corporate / YOURSELF</p>"
                    . "<ol>"
                    . "<li>As member of the [Community Name] Body Corporate you have an obligation to pay overdue contributions, charges and interest immediately.</li>"
                    . "<li>You are currently in arrears with the contributions and charges owed to <strong>[Community Name] Body Corporate</strong> in the amount of R[Balance].</li>"
                    . "<li>Interest is being charged on your arrears at a rate of [Interest Percent]% [Interest Period]</li>"
                    . "<li>This letter serves as a demand to yourself to pay R[Balance], plus the cost of the letter of demand in the amount of <strong>R[Fine Amount]</strong> within <strong>14 (fourteen) days</strong> from date hereof, failing which legal actions will be taken against you for the recovery of the owed amount, interest thereon and legal costs on an attorney-and-client scale.</li>"
                    . "</ol>"
                    . "<p>Regards,<br>Yours in Management</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'handover_owner',
                'name'    => 'Handover Notice - Owner',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'HAND OVER OF ACCOUNT',
                'body'    => "<p><strong>ATT: [Customer Name]</strong></p>"
                    . "<p>RE: Handover of account for [Customer Code], [Community Name] : [Customer Name]</p>"
                    . "<p>Please be advised that your account relating to [Customer Code] in [Community Name] has been handed over for collection to the Attorneys below:</p>"
                    . "<p>[Attorney Name]<br>[Attorney Email]<br>[Attorney Number]</p>"
                    . "<p>For more information please contact our offices.</p>"
                    . "<p>Regards</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'handover_attorney',
                'name'    => 'Handover Notice - Attorney',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => 'HAND OVER OF ACCOUNT',
                'body'    => "<p>Good day,</p>"
                    . "<p>RE: Handover of account for [Customer Code], [Community Name] : [Customer Name]</p>"
                    . "<p>Please be advised that the account for [Customer Name] relating to [Customer Code] in [Community Name] is hereby handed over for collection.</p>"
                    . "<p>Please log into the portal to access all the relevant information about this customer.</p>"
                    . "<p>Regards</p>",
                'terms'   => null,
            ],
            [
                'key'     => 'fine',
                'name'    => 'Fine Message',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => '[Community Name] : FINE - [Unit Address]',
                'body'    => "<p>Dear Owner,</p>"
                    . "<p>We write to you on behalf of [Community Name]</p>"
                    . "<p>Community living requires a heightened sense of neighbourliness as well as an awareness of and compliance with the rules and regulations by all residents in an effort to create a harmonious and pleasant lifestyle.</p>"
                    . "<p>Copies of the regulatory documents are available on request.</p>",
                'terms'   => "<p><strong>It is important to take note of the following:</strong></p>"
                    . "<ol>"
                    . "<li>As owner you are responsible for payment of all fines relating to your property caused by yourself, persons under your control and/or employed by you.</li>"
                    . "<li>The amount due for the fine, if any, will reflect on your next levy statement and will be payable together with your levy for the particular statement month.</li>"
                    . "<li>Should you be of the opinion that this complaint is unfairly directed at you and wish to make a representation, please do so in writing within 7 (seven) days.</li>"
                    . "<li>Lastly, kindly note that a [Admin Fee] administration fee will be charged for the issuing of this fine letter - please see attached invoice and customer statement reflecting same. If your PDF is password-protected, use your customer code to access it.</li>"
                    . "</ol>",
            ],
            [
                'key'     => 'penalty',
                'name'    => 'Penalty Message',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => '[Community Name] : PENALTY - [Unit Address]',
                'body'    => "<p>Dear Owner,</p>"
                    . "<p>We write to you on behalf of [Community Name]</p>"
                    . "<p>Community living requires a heightened sense of neighbourliness as well as an awareness of and compliance with the rules and regulations by all residents in an effort to create a harmonious and pleasant lifestyle.</p>"
                    . "<p>Copies of the regulatory documents are available on request.</p>",
                'terms'   => "<p><strong>It is important to take note of the following:</strong></p>"
                    . "<ol>"
                    . "<li>As owner you are responsible for payment of all penalties relating to your property caused by yourself, persons under your control and/or employed by you.</li>"
                    . "<li>The amount due for the penalty, if any, will reflect on your next levy statement and will be payable together with your levy for the particular statement month.</li>"
                    . "<li>Should you be of the opinion that this complaint is unfairly directed at you and wish to make a representation, please do so in writing within 7 (seven) days.</li>"
                    . "<li>Lastly, kindly note that a [Admin Fee] administration fee will be charged for the issuing of this penalty letter - please see attached invoice and customer statement reflecting same. If your PDF is password-protected, use your customer code to access it.</li>"
                    . "</ol>",
            ],
            [
                'key'     => 'warning',
                'name'    => 'Warning Message',
                'type'    => MessageTemplateType::EMAIL->value,
                'subject' => '[Community Name] : WARNING - [Unit Address]',
                'body'    => "<p>Dear Owner,</p>"
                    . "<p>We write to you on behalf of [Community Name]</p>"
                    . "<p>Community living requires a heightened sense of neighbourliness as well as an awareness of and compliance with the rules and regulations by all residents in an effort to create a harmonious and pleasant lifestyle.</p>"
                    . "<p>Copies of the regulatory documents are available on request.</p>",
                'terms'   => "<p><strong>It is important to take note of the following:</strong></p>"
                    . "<ol>"
                    . "<li>As owner you are responsible for payment of all penalties relating to your property caused by yourself, persons under your control and/or employed by you.</li>"
                    . "<li>The amount due for the penalty, if any, will reflect on your next levy statement and will be payable together with your levy for the particular statement month.</li>"
                    . "<li>Should you be of the opinion that this complaint is unfairly directed at you and wish to make a representation, please do so in writing within 7 (seven) days.</li>"
                    . "</ol>",
            ],
            [
                'key'     => 'first_notice_sms',
                'name'    => '1st Notice SMS',
                'type'    => MessageTemplateType::SMS->value,
                'subject' => null,
                'body'    => "Hi [Customer Name],\nYour outstanding balance is R[Balance].\nFriendly reminder to settle your account by using [Customer Code] as a payment reference.",
                'terms'   => null,
            ],
            [
                'key'     => 'second_notice_sms',
                'name'    => '2nd Notice SMS',
                'type'    => MessageTemplateType::SMS->value,
                'subject' => null,
                'body'    => "Hi [Customer Name],\nYour outstanding balance is R[Balance].\nSecond Reminder to settle your account by using [Customer Code] as a payment reference.",
                'terms'   => null,
            ],
            [
                'key'     => 'lod_sms',
                'name'    => 'Letter of Demand SMS',
                'type'    => MessageTemplateType::SMS->value,
                'subject' => null,
                'body'    => "Hi [Customer Name],\nFinal reminder to settle your outstanding balance of R[Balance].\nYour account will be handed over to an attorney for further legal actions.",
                'terms'   => null,
            ],
        ];
    }

    /**
     * The Insert-Tag placeholder tokens offered in the template editor.
     *
     * @return array<int, string>
     */
    public static function tags(): array
    {
        return [
            '[Customer Name]',
            '[Customer Code]',
            '[Community Name]',
            '[Balance]',
            '[Interest Percent]',
            '[Interest Period]',
            '[Fine Amount]',
            '[Admin Fee]',
            '[Days Label]',
            '[Unit Address]',
            '[Attorney Name]',
            '[Attorney Email]',
            '[Attorney Number]',
        ];
    }

    /**
     * Find a single default definition by key.
     *
     * @param string $key
     * @return array{key: string, name: string, type: string, subject: ?string, body: string, terms: ?string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $default) {
            if ($default['key'] === $key) {
                return $default;
            }
        }

        return null;
    }
}
