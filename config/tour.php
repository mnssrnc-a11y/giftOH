<?php

/*
|--------------------------------------------------------------------------
| Tutorials: one short, hands-on walkthrough for every part of the system
|--------------------------------------------------------------------------
| A tutorial starts by itself the first time someone opens its page (or workspace section), and
| any time from the "?" button at the top of the page or from Help & tour.
|
| roles    who sees it (user = requester, admin, super_admin)
| route    the page it runs on (a route name); section = the workspace section (#funding, #accounts…)
| record   true when the page belongs to one request, so Help opens the newest one
|
| Each step points at a part of the page: "target" is a data-tour="..." name or a CSS selector,
| with alternatives in order (the sidebar on a computer, the bottom bar on a phone). A step whose
| target is not on the page is left out; a step without a target is shown in the middle.
| "do" makes it hands-on: click = the person clicks the highlighted part, fill = types or chooses
| in it. The tutorial moves on by itself when they do. "next" starts that tutorial on the page the
| click opens. "when" marks a step that shows only at one stage of a request (its target is on the
| page only then); at most 4 steps show at a time.
*/

return [

    'tutorials' => [

        /* ---------- Requester ---------- */

        'welcome' => [
            'roles' => ['user'], 'route' => 'dashboarduser',
            'title' => 'Getting started', 'about' => 'A one-minute look around your home page.',
            'steps' => [
                ['title' => 'Welcome!', 'text' => 'Gift of Hope helps your community ask the foundation for help. Here is a quick look around.'],
                ['target' => ['bell', 'tab-alerts'], 'title' => 'Notifications', 'text' => 'Every update on your requests shows up here.'],
                ['target' => ['nav', 'tabbar'], 'title' => 'Menu', 'text' => 'Your requests, activity, profile and settings.'],
                ['target' => 'new-request', 'do' => 'click', 'next' => 'fund-request', 'title' => 'Your turn', 'text' => 'Click "Create fund request" to start one.'],
            ],
        ],

        'fund-request' => [
            'roles' => ['user'], 'route' => 'fund-request',
            'title' => 'Ask for help', 'about' => 'Fill in the six-step fund request.',
            'steps' => [
                ['target' => '.fr-stepper', 'title' => 'Six short steps', 'text' => 'Tap any step to jump to it.'],
                ['target' => '#org_choice', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Choose your organization. Not listed? Pick "Others" and type its name.'],
                ['target' => '.fr-legend', 'title' => 'Required fields', 'text' => 'Fields with * are required. Anything missing is listed before you submit.'],
                ['target' => '[data-fr-next]', 'do' => 'click', 'title' => 'Your turn', 'text' => 'Click Next. If something is missing, it tells you what.'],
            ],
        ],

        'request-status' => [
            'roles' => ['user'], 'route' => 'request-status',
            'title' => 'Follow your requests', 'about' => 'See where each request is.',
            'steps' => [
                ['target' => '.hope-toolbar input', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search a request by its title or ID.'],
                ['target' => '.hope-table tbody tr td:nth-child(4)', 'title' => 'Status', 'text' => 'Under assessment → Under review → Approved → Completed. Approved ones show the donations still needed.'],
                ['target' => '[data-open-dialog^="request-"]', 'title' => 'Timeline', 'text' => 'Open the timeline to see every step, or to appeal a denial.'],
            ],
        ],

        'request-detail' => [
            'roles' => ['user'], 'route' => 'fund-request.show', 'record' => true,
            'title' => 'Your request page', 'about' => 'Follow it, answer the interview, list the people, message or cancel.',
            'steps' => [
                ['target' => '#progress', 'title' => 'Progress', 'text' => 'Where your request is now. Until the funds are sent, you can also cancel it here.'],
                ['when' => 'pending', 'target' => 'interview', 'title' => 'Interview', 'text' => 'Confirm the interview with "I\'ll be there", or ask for another time.'],
                ['when' => 'pending', 'target' => '#documents form', 'title' => 'Documents', 'text' => 'A document needs a clearer copy. Upload it here.'],
                ['when' => 'approved', 'target' => '#funding-gap', 'title' => 'Your funds', 'text' => 'Set aside for you, or how much in donations is still missing before your transfer.'],
                ['when' => 'approved', 'target' => '#release [data-people-add]', 'do' => 'click', 'title' => 'Your turn', 'text' => 'Add each person who gets the help. Same name twice? Give each a number in the "No." box.'],
                ['when' => 'released', 'target' => '#liquidation', 'title' => 'Liquidation', 'text' => 'After the funds arrive, upload the receipts and the list of people who got the help.'],
                ['target' => '#messages textarea', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Write a message to the foundation.'],
            ],
        ],

        'activity' => [
            'roles' => ['user'], 'route' => 'activity',
            'title' => 'Your activity', 'about' => 'Funds received and your success rate.',
            'steps' => [
                ['target' => '.hope-grid.four', 'title' => 'Your numbers', 'text' => 'Funds received, requests and success rate.'],
                ['target' => '.hope-table', 'title' => 'By month', 'text' => 'Funds released to you, month by month.'],
            ],
        ],

        'profile' => [
            'roles' => ['user'], 'route' => 'user',
            'title' => 'Your profile', 'about' => 'Your details and shortcuts.',
            'steps' => [
                ['target' => '.hope-detail', 'title' => 'Your details', 'text' => 'The information on your account.'],
                ['target' => '.hope-heading .hope-button', 'title' => 'Edit', 'text' => 'Change your photo and preferences here.'],
            ],
        ],

        /* ---------- Every role ---------- */

        'settings' => [
            'roles' => ['user', 'admin', 'super_admin'], 'route' => 'settings',
            'title' => 'Settings', 'about' => 'Photo, sign-in codes and dark mode.',
            'steps' => [
                ['target' => 'form[action$="/settings/profile-picture"]', 'title' => 'Profile picture', 'text' => 'Choose a photo, then save it.'],
                ['target' => '#email-preference', 'title' => 'Sign-in codes', 'text' => 'On: signing in asks for a code sent to your email.'],
                ['target' => '#dark-preference', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Switch dark mode on or off. It saves by itself.'],
            ],
        ],

        'notifications' => [
            'roles' => ['user', 'admin', 'super_admin'], 'route' => 'notifications',
            'title' => 'Notifications', 'about' => 'Open an update to go straight to it.',
            'steps' => [
                ['target' => '.hope-notice-row', 'title' => 'Open one', 'text' => 'Click a notification to open the exact part it is about: the documents, the messages, the release…'],
                ['target' => 'form[action$="/notifications/read-all"] button', 'title' => 'All read', 'text' => 'Clear them all at once.'],
            ],
        ],

        'edit-profile' => [
            'roles' => ['user', 'admin', 'super_admin'], 'route' => 'user.edit',
            'title' => 'Edit your details', 'about' => 'Name and phone number.',
            'steps' => [
                ['target' => '#fname', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Fix your name or phone number, then save.'],
            ],
        ],

        'change-password' => [
            'roles' => ['user', 'admin', 'super_admin'], 'route' => 'change-password.form',
            'title' => 'Change your password', 'about' => 'Keep your account safe.',
            'steps' => [
                ['target' => '#current_password', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Type your current password, then the new one twice.'],
            ],
        ],

        /* ---------- Admin ---------- */

        'admin-welcome' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'dashboard',
            'title' => 'Getting started', 'about' => 'A one-minute look around.',
            'steps' => [
                ['title' => 'Welcome!', 'text' => 'You assess requests and recommend a decision, then send the approved funds and check the liquidation.'],
                ['target' => 'release-line', 'title' => 'Release line', 'text' => 'Approved requests get the donations first come, first served. You are told when one is ready to transfer.'],
                ['target' => ['bell', 'tab-alerts'], 'title' => 'Notifications', 'text' => 'New requests, replies, documents and funds ready to transfer. Each one opens the right part of the request.'],
                ['target' => ['nav-funding', 'tab-funding'], 'do' => 'click', 'next' => 'admin-funding', 'title' => 'Your turn', 'text' => 'Click Funding.'],
            ],
        ],

        'admin-funding' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'funding',
            'title' => 'Funding', 'about' => 'Find the requests that need you.',
            'steps' => [
                ['target' => '[data-funding-tabs]', 'title' => 'By step', 'text' => 'Requests by step: assessment, decision, release, liquidation, then done.'],
                ['target' => '.admin-table-action', 'title' => 'Open a request', 'text' => 'Click a request\'s name or ⌕ to work on it.'],
                ['target' => '[data-funding-search]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search a project, organization or requester.'],
            ],
        ],

        'admin-request' => [
            'roles' => ['admin'], 'route' => 'admin.fund-request.show', 'record' => true,
            'title' => 'Work on a request', 'about' => 'The procedure, one step at a time.',
            'steps' => [
                ['target' => 'procedure', 'title' => 'Step by step', 'text' => 'Interview, documents, assessment, decision, release, liquidation. ✓ means done; release opens after approval.'],
                ['target' => '.proc-panel:not([hidden])', 'title' => 'This step', 'text' => 'Only what this step needs is shown. Finish it, then press "Next".'],
                ['target' => 'info-tabs', 'title' => 'About the request', 'text' => 'The request, verification notes, the conversation, transactions and history.'],
            ],
        ],

        'admin-updates' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'updates',
            'title' => 'Post an update', 'about' => 'Announcements with photos.',
            'steps' => [
                ['target' => '#post-title', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Type a title.'],
                ['target' => '#post-image', 'title' => 'Photo', 'text' => 'Add a photo if you like. Two or more posts play as a slideshow.'],
                ['target' => '[data-compile-post]', 'title' => 'Quick update', 'text' => 'Or build one from the current requests.'],
                ['target' => '[data-post-form] button[type="submit"]', 'title' => 'Publish', 'text' => 'Public posts also show on the landing page.'],
            ],
        ],

        'admin-reports' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'reports',
            'title' => 'Reports', 'about' => 'Filter, export and print.',
            'steps' => [
                ['target' => '.admin-report-tabs', 'title' => 'Choose a report', 'text' => 'Boxes, funding, funded and finished projects.'],
                ['target' => '[data-report-from]', 'title' => 'Dates', 'text' => 'Narrow it down by date or search.'],
                ['target' => '[data-export-csv]', 'title' => 'Export', 'text' => 'Save it as CSV, or print / save as PDF.'],
            ],
        ],

        'admin-settings' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'settings',
            'title' => 'Your settings', 'about' => 'Information, photo, security.',
            'steps' => [
                ['target' => '.admin-settings-nav', 'title' => 'Settings', 'text' => 'Information, photo, preferences, security and AI.'],
                ['target' => '[data-settings-tab="security"]', 'do' => 'click', 'title' => 'Your turn', 'text' => 'Open Security to change your password.'],
            ],
        ],

        'donations' => [
            'roles' => ['admin'], 'route' => 'donations',
            'title' => 'Donations', 'about' => 'Money collected and available.',
            'steps' => [
                ['target' => '.admin-funding-summary', 'title' => 'Totals', 'text' => 'Collected by the boxes and recorded donations, the funds on hand after transfers, and what is free to grant.'],
            ],
        ],

        'iot-monitor' => [
            'roles' => ['admin', 'super_admin'], 'route' => 'iot-monitor',
            'title' => 'IoT box monitor', 'about' => 'The smart donation boxes, live.',
            'steps' => [
                ['target' => ['iot-stats', '#stat-active-boxes'], 'title' => 'Live', 'text' => 'Active boxes, total collected and alerts.'],
                ['target' => '#boxes-tbody', 'title' => 'Each box', 'text' => 'Its location, status, last signal and total collected.'],
            ],
        ],

        /* ---------- Super admin ---------- */

        'sa-welcome' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'dashboard',
            'title' => 'Getting started', 'about' => 'A one-minute look around.',
            'steps' => [
                ['title' => 'Welcome!', 'text' => 'You make the final decision and look after the system. Here is a quick look around.'],
                ['target' => 'stats', 'title' => 'At a glance', 'text' => 'Accounts, boxes, decisions waiting and approval rate.'],
                ['target' => 'awaiting', 'title' => 'Waiting for you', 'text' => 'Requests the admins have reviewed, waiting for your final decision.'],
                ['target' => ['nav-requests', 'tab-requests'], 'do' => 'click', 'next' => 'sa-requests', 'title' => 'Your turn', 'text' => 'Click Fund requests.'],
            ],
        ],

        'sa-requests' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'requests',
            'title' => 'Fund requests', 'about' => 'Find a request to decide.',
            'steps' => [
                ['target' => '[data-sa-request-filter]', 'title' => 'Filter', 'text' => 'Filter by status. Requests waiting for your decision are listed first.'],
                ['target' => '[data-sa-requests] a[href*="/superadmin/fund-request/"]', 'title' => 'Open a request', 'text' => 'Open one for the final check.'],
                ['target' => '[data-sa-request-search]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search an organization or request.'],
            ],
        ],

        'sa-request' => [
            'roles' => ['super_admin'], 'route' => 'superadmin.fund-request.show', 'record' => true,
            'title' => 'Final check', 'about' => 'Approve or reject, then send the funds.',
            'steps' => [
                ['target' => '.sa-decision', 'title' => 'Decide', 'text' => 'Approve with an amount, or reject with a reason.'],
                ['target' => '#documents', 'title' => 'Check', 'text' => 'Documents, amount needed, AI assessment and verification notes are below.'],
                ['target' => '#transfer', 'title' => 'Transfer', 'text' => 'After approval, follow the transfer here. If bank transfers are connected, you send it with an emailed code.'],
            ],
        ],

        'sa-accounts' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'accounts',
            'title' => 'Accounts', 'about' => 'Roles and access.',
            'steps' => [
                ['target' => '[data-sa-filter-search="accounts"]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search a name or email.'],
                ['target' => '[data-sa-account]', 'title' => 'Manage', 'text' => 'Change the role, or turn access on or off.'],
            ],
        ],

        'sa-settings' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'settings',
            'title' => 'System settings', 'about' => 'Scoring, request interval, email and bank transfers.',
            'steps' => [
                ['target' => 'sa-scores', 'title' => 'AI scoring', 'text' => 'How much each kind of need counts when funds are limited.'],
                ['target' => 'sa-interval', 'title' => 'Request interval', 'text' => 'How long an account waits between requests.'],
                ['target' => '#settings-mail', 'title' => 'Email', 'text' => 'The email that sends sign-in codes and notices. Check it, then send a test email.'],
                ['target' => '#settings-payouts', 'title' => 'Bank transfers', 'text' => 'How approved funds reach bank accounts and e-wallets, and the payout balance.'],
            ],
        ],

        'sa-monitor' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'monitor',
            'title' => 'System monitor', 'about' => 'Boxes, outcomes and box sign-ins.',
            'steps' => [
                ['target' => '[data-sa-page="monitor"] .sa-grid', 'title' => 'At a glance', 'text' => 'Donation boxes and funding outcomes.'],
                ['target' => '#monitor-boxes', 'title' => 'Box sign-ins', 'text' => 'Give each donation box its own sign-in so no one else can change its totals.'],
            ],
        ],

        'sa-transactions' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'transactions',
            'title' => 'Transaction log', 'about' => 'Every money step, never edited.',
            'steps' => [
                ['target' => 'ledger-check', 'title' => 'Checked', 'text' => '✓ means no entry was changed. Entries cannot be edited or deleted.'],
                ['target' => '.tx-table', 'title' => 'Every peso', 'text' => 'Approvals, funds set aside, transfers, cancellations and liquidations.'],
            ],
        ],

        'sa-activity' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'activity',
            'title' => 'Activity log', 'about' => 'Every change, recorded.',
            'steps' => [
                ['target' => '[data-sa-filter-search="logs"]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search an action or a person.'],
            ],
        ],
    ],
];
