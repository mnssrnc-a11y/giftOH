<?php

/*
|--------------------------------------------------------------------------
| Guided tour for new users
|--------------------------------------------------------------------------
| Shown once on each role's home page the first time someone signs in (and any time from
| Help & tour). Raise "version" when the steps change a lot, so everyone sees the new tour once.
|
| Each step points at an element with data-tour="..."; "targets" lists alternatives in order
| (for example the sidebar on a computer, the bottom bar on a phone). A step without a visible
| target is shown in the middle of the screen.
*/

return [

    'version' => 1,

    'steps' => [

        'user' => [
            ['title' => 'Welcome to Gift of Hope', 'text' => 'Gift of Hope helps organizations ask the foundation for help for their community. This short tour shows you around. It takes about a minute.'],
            ['targets' => ['new-request'], 'title' => 'Ask for help', 'text' => 'Start a fund request here. It has six short steps: your organization, address, what you need, where to receive the funds, and two documents (a formal letter and a Certificate of Indigency).'],
            ['targets' => ['summary'], 'title' => 'Follow your request', 'text' => 'See how many requests you have and where each one is. After you submit, your request is "Under assessment" until a social worker interviews you, usually within 7 days.'],
            ['targets' => ['updates'], 'title' => 'News from the foundation', 'text' => 'Announcements and funding updates from the foundation appear here, with photos.'],
            ['targets' => ['bell'], 'title' => 'Notifications', 'text' => 'The bell tells you when something changes: an interview is scheduled, a document needs a new copy, your request is decided, or the funds are sent. Open one to go straight to it.'],
            ['targets' => ['nav', 'tabbar'], 'title' => 'Getting around', 'text' => 'Use this menu for Request status, My activity, your profile and Settings. In Settings you can turn on sign-in codes by email and dark mode.'],
            ['title' => 'How a request moves', 'text' => '1. You submit. 2. A social worker interviews you. 3. An admin reviews it and sets a budget per person. 4. The super admin gives the final approval. 5. You list the people who will receive the help. 6. The funds are sent to your account. 7. You report how the money was used (receipts and the list of recipients) within 30 days.'],
            ['targets' => ['help', 'tab-menu'], 'title' => 'Help is always here', 'text' => 'Open Help & tour any time to read how everything works or to take this tour again.'],
        ],

        'admin' => [
            ['title' => 'Welcome, admin', 'text' => 'You review each request with the social workers and recommend a decision. This tour shows where everything is.'],
            ['targets' => ['stats'], 'title' => 'Today at a glance', 'text' => 'Pending requests, smart donation boxes, donations and the funds available to grant.'],
            ['targets' => ['nav-funding', 'tab-funding'], 'title' => 'Funding', 'text' => 'Open a request to schedule the interview, record the social worker\'s assessment, verify the documents, add verification notes and set the budget per person from the price list. Then recommend approval or rejection: the super admin makes the final decision.'],
            ['targets' => ['nav-funding', 'tab-funding'], 'title' => 'After approval', 'text' => 'The requester lists the people who will receive the help. Then you send the funds and record the release with the proof, and later check the liquidation (receipts and recipients).'],
            ['targets' => ['nav-updates', 'tab-updates'], 'title' => 'Updates', 'text' => 'Post announcements, with a photo if you like. Two or more play as a slideshow for users and on the landing page.'],
            ['targets' => ['nav-reports', 'tab-menu'], 'title' => 'Reports and monitoring', 'text' => 'Reports cover funding, funded and finished projects, IoT boxes and the price list. The IoT box monitor shows each smart donation box live.'],
            ['targets' => ['bell'], 'title' => 'Notifications', 'text' => 'You are told about new requests, messages from requesters, interview answers, new documents, lists of people, liquidations, appeals and the super admin\'s decisions.'],
            ['targets' => ['help', 'tab-menu'], 'title' => 'Help is always here', 'text' => 'Open Help & tour any time to read the full process or take this tour again.'],
        ],

        'super_admin' => [
            ['title' => 'Welcome, super admin', 'text' => 'You make the final decision on every request and look after the whole system. This tour shows where everything is.'],
            ['targets' => ['stats'], 'title' => 'The system at a glance', 'text' => 'Accounts, connected donation boxes, requests waiting for your decision, and the approval rate.'],
            ['targets' => ['awaiting'], 'title' => 'Your decisions', 'text' => 'Requests the admins have reviewed wait here. Open one for the final check: the requester\'s account, documents, assessment, budget, the admin\'s notes, the AI assessment and the conversation. Then approve or reject.'],
            ['targets' => ['nav-requests', 'tab-requests'], 'title' => 'Fund requests and transfers', 'text' => 'Every request is listed here. Once a request is approved and its list of people is in, you can send the funds from the request page when automatic transfers are connected.'],
            ['targets' => ['nav-accounts', 'tab-menu'], 'title' => 'Accounts', 'text' => 'Change roles and turn access on or off for any account.'],
            ['targets' => ['nav-prices', 'tab-prices'], 'title' => 'Price list', 'text' => 'Only you add items and set prices (Food, Medical, Cleaning materials). Admins use them for budgets. "Update prices now" checks the DTI bulletin, the TGP store and web search in the background.'],
            ['targets' => ['nav-settings', 'tab-menu'], 'title' => 'System settings', 'text' => 'AI scoring priorities, the time between requests, and email: save the Brevo API key and sender here so verification codes and notices can be sent.'],
            ['targets' => ['bell'], 'title' => 'Notifications', 'text' => 'You are told when a request is ready for your decision, a list of people is in, a transfer completes, a price update ends, and when new accounts sign up.'],
            ['targets' => ['help', 'tab-menu'], 'title' => 'Help is always here', 'text' => 'Open Help & tour any time to read the full process or take this tour again.'],
        ],
    ],
];
