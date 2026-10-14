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
| click opens.
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
                ['target' => '.hope-table tbody tr td:nth-child(4)', 'title' => 'Status', 'text' => 'Under assessment → Under review → Approved → Completed.'],
                ['target' => '[data-open-dialog^="request-"]', 'title' => 'Timeline', 'text' => 'Open the timeline to see every step, or to appeal a denial.'],
            ],
        ],

        'request-detail' => [
            'roles' => ['user'], 'route' => 'fund-request.show', 'record' => true,
            'title' => 'Your request page', 'about' => 'Follow it, list the people, message or cancel.',
            'steps' => [
                ['target' => '#progress', 'title' => 'Progress', 'text' => 'Where your request is and what to do next, like sending a clearer document.'],
                ['target' => '[data-people-add]', 'do' => 'click', 'title' => 'Your turn', 'text' => 'Click "Add person" and type the name of each person who will receive the help.'],
                ['target' => '#messages textarea', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Write a message to the foundation.'],
                ['target' => '#cancel', 'title' => 'Cancel', 'text' => 'Changed your mind? Cancel here until the funds are sent. Everything still to come stops.'],
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
                ['target' => '.hope-notice-row', 'title' => 'Open one', 'text' => 'Click a notification to go straight to it.'],
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
                ['title' => 'Welcome!', 'text' => 'You review requests and recommend a decision. Here is a quick look around.'],
                ['target' => 'stats', 'title' => 'Today', 'text' => 'Pending requests, donation boxes and funds.'],
                ['target' => ['bell', 'tab-alerts'], 'title' => 'Notifications', 'text' => 'New requests and replies show up here.'],
                ['target' => ['nav-funding', 'tab-funding'], 'do' => 'click', 'next' => 'admin-funding', 'title' => 'Your turn', 'text' => 'Click Funding.'],
            ],
        ],

        'admin-funding' => [
            'roles' => ['admin'], 'route' => 'admin', 'section' => 'funding',
            'title' => 'Funding', 'about' => 'Find the requests that need you.',
            'steps' => [
                ['target' => '[data-funding-tabs]', 'title' => 'By step', 'text' => 'Assessment, decision, release and liquidation.'],
                ['target' => '.admin-table-action', 'title' => 'Open a request', 'text' => 'Click ⌕ to work on a request.'],
                ['target' => '[data-funding-search]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search a project, organization or requester.'],
            ],
        ],

        'admin-request' => [
            'roles' => ['admin'], 'route' => 'admin.fund-request.show', 'record' => true,
            'title' => 'Work on a request', 'about' => 'Interview, amount needed, documents, release.',
            'steps' => [
                ['target' => 'next-step', 'title' => 'Next step', 'text' => 'Interview, then the assessment with the amount needed, then release and liquidation.'],
                ['target' => '#documents', 'title' => 'Documents', 'text' => 'Verify each one, or ask for a clearer copy.'],
                ['target' => '#notes', 'title' => 'Notes', 'text' => 'Notes for the super admin. The requester never sees them.'],
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
                ['target' => '.admin-funding-summary', 'title' => 'Totals', 'text' => 'Collected by the boxes, recorded, and still available to grant.'],
            ],
        ],

        'iot-monitor' => [
            'roles' => ['admin', 'super_admin'], 'route' => 'iot-monitor',
            'title' => 'IoT box monitor', 'about' => 'The smart donation boxes, live.',
            'steps' => [
                ['target' => '#stat-active-boxes', 'title' => 'Live', 'text' => 'Boxes online, today\'s total and alerts.'],
                ['target' => '#boxes-tbody', 'title' => 'Each box', 'text' => 'Its place, total and last signal.'],
            ],
        ],

        /* ---------- Super admin ---------- */

        'sa-welcome' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'dashboard',
            'title' => 'Getting started', 'about' => 'A one-minute look around.',
            'steps' => [
                ['title' => 'Welcome!', 'text' => 'You make the final decision and look after the system. Here is a quick look around.'],
                ['target' => 'stats', 'title' => 'At a glance', 'text' => 'Accounts, boxes, decisions waiting and approval rate.'],
                ['target' => 'awaiting', 'title' => 'Waiting for you', 'text' => 'Requests the admins have reviewed.'],
                ['target' => ['nav-requests', 'tab-requests'], 'do' => 'click', 'next' => 'sa-requests', 'title' => 'Your turn', 'text' => 'Click Fund requests.'],
            ],
        ],

        'sa-requests' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'requests',
            'title' => 'Fund requests', 'about' => 'Find a request to decide.',
            'steps' => [
                ['target' => '[data-sa-request-filter]', 'title' => 'Filter', 'text' => 'Those waiting for your decision come first.'],
                ['target' => '[data-sa-requests] a[href*="/superadmin/fund-request/"]', 'title' => 'Open a request', 'text' => 'Open one for the final check.'],
                ['target' => '[data-sa-request-search]', 'do' => 'fill', 'title' => 'Your turn', 'text' => 'Search an organization or request.'],
            ],
        ],

        'sa-request' => [
            'roles' => ['super_admin'], 'route' => 'superadmin.fund-request.show', 'record' => true,
            'title' => 'Final check', 'about' => 'Approve or reject, then send the funds.',
            'steps' => [
                ['target' => '.sa-decision', 'title' => 'Decide', 'text' => 'Approve with an amount, or reject with a reason.'],
                ['target' => '#documents', 'title' => 'Check', 'text' => 'Documents, assessment, amount needed, AI score and notes are below.'],
                ['target' => '#transfer', 'title' => 'Send the funds', 'text' => 'After approval, send the money here. You confirm with an email code.'],
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
            'title' => 'System settings', 'about' => 'Scoring, request interval, email.',
            'steps' => [
                ['target' => 'sa-scores', 'title' => 'AI scoring', 'text' => 'How much each kind of need counts.'],
                ['target' => 'sa-interval', 'title' => 'Request interval', 'text' => 'How long an account waits between requests.'],
                ['target' => '#settings-mail', 'title' => 'Email', 'text' => 'Save the Brevo key and sender, then send a test email.'],
            ],
        ],

        'sa-monitor' => [
            'roles' => ['super_admin'], 'route' => 'superadmin', 'section' => 'monitor',
            'title' => 'System monitor', 'about' => 'Boxes, outcomes and events.',
            'steps' => [
                ['target' => '[data-sa-page="monitor"] .sa-grid', 'title' => 'At a glance', 'text' => 'Donation boxes and funding outcomes.'],
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
