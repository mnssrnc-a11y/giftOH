<?php

/*
|--------------------------------------------------------------------------
| Funding request rules
|--------------------------------------------------------------------------
| Based on the foundation interview:
| - Every request needs a formal request letter and a Certificate of Indigency.
| - Any organization may apply. (Education / scholarship assistance is no longer offered.)
| - A request covers 20 to 40 individuals; the budget is planned per person.
| - Social workers interview and assess each request before approval, within a week.
| - Funds are sent by bank-to-bank transfer to an account in the receiver's name.
| - After release, the organization liquidates: receipts and the list of people who received help.
*/

return [

    'beneficiaries' => [
        'min' => (int) env('FUNDING_MIN_BENEFICIARIES', 20),
        'max' => (int) env('FUNDING_MAX_BENEFICIARIES', 40),
    ],

    // Days the social workers have to interview and assess a new request.
    'assessment_days' => (int) env('FUNDING_ASSESSMENT_DAYS', 7),

    // Days after funds are released before the liquidation report is overdue.
    'liquidation_days' => (int) env('FUNDING_LIQUIDATION_DAYS', 30),

    // Per-person budget the foundation usually allocates for goods (e.g. 40 people x ₱500 = ₱20,000).
    'default_per_person_allocation' => (float) env('FUNDING_PER_PERSON_ALLOCATION', 500),

    'document_rules' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',

    // Request files (IDs, certificates, receipts...) are private: served only to the requester and staff.
    'files_disk' => env('FUNDING_FILES_DISK', 'local'),

    // Documents every request must include.
    'requirements' => [
        'formal_letter' => ['label' => 'Formal request letter', 'hint' => 'Signed letter stating what the money will be used for and for whom.'],
        'indigency_cert' => ['label' => 'Certificate of Indigency', 'hint' => 'Issued by the barangay / municipal social welfare office.'],
    ],

    /*
     | Categories. "per_person_cap" is the usual allocation per beneficiary used by the AI advisor.
     | "priority" is the default priority score (0-100), which the super admin can override in
     | Firebase at system_settings/category_priorities.
     */
    'categories' => [
        'Medical' => ['label' => 'Medical assistance / medicines', 'per_person_cap' => 500, 'priority' => 95, 'aliases' => ['healthcare', 'medicine']],
        'Food' => ['label' => 'Food packs / groceries', 'per_person_cap' => 500, 'priority' => 75, 'aliases' => ['food & shelter']],
        'Hygiene & Cleaning Supplies' => ['label' => 'Hygiene & cleaning supplies', 'per_person_cap' => 500, 'priority' => 70, 'aliases' => ['cleaning', 'hygiene']],
        'Shelter' => ['label' => 'Shelter / home repair', 'per_person_cap' => 500, 'priority' => 80, 'aliases' => []],
        'Emergency Relief' => ['label' => 'Emergency / disaster relief', 'per_person_cap' => 500, 'priority' => 90, 'aliases' => []],
        'Community Development' => ['label' => 'Community development', 'per_person_cap' => 500, 'priority' => 65, 'aliases' => ['community']],
    ],

    'disbursement_methods' => [
        'bank_transfer' => 'Bank-to-bank transfer',
    ],

    'assessment_modes' => [
        'home_visit' => 'Home / site visit',
        'office' => 'Interview at the foundation office',
        'phone' => 'Phone or video call',
    ],

    // Shown as suggestions; any organization may apply.
    'known_organizations' => [
        "The Children's Home of Eucharistic Love and Kindness",
        "Children's Home of the Immaculate Heart of Mary",
        "Children's Joy Foundation Inc. - Pampanga",
        'Charity Home for the Elderly',
        'Tuloy Pampanga',
        'Bahay Pag-Ibig Home for the Aged',
        "Ima's Home for Children",
        'Munting Tahanan ng Nazareth',
        'Send The Light Ministries for the Filipino',
        'Domus Pastorum Foundation Incorporated',
        "Duyan Ni Maria Children's Home",
    ],
];
