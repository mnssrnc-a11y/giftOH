<?php

/*
|--------------------------------------------------------------------------
| Associated with SACOP
|--------------------------------------------------------------------------
| Communities, organizations and parishes connected with the Social Action Center of Pampanga
| (SACOP), with the SACOP page that shows the connection. The landing page shows them as a
| slideshow, the fund request form lists them as organizations, and staff see an "Associated with
| SACOP" mark on their requests.
|
| photo   a photo posted on that SACOP page (served by sacop.ph's image CDN), a photo in
|         public/images/associated (the parishes), or null
| alt     what the photo shows, when the credit does not say it
| credit  where the photo comes from (resettlement photos show SACOP's resettlements in general),
|         or null when it is not known
| location  null when SACOP's page does not say
*/

$cdn = fn (string $host, string $path) => "https://{$host}.wp.com/sacop.ph/wp-content/uploads/{$path}?resize=960%2C600&ssl=1";
$resettlements = 'https://sacop.ph/resettlements/';
$about = 'https://sacop.ph/about/';
$resettlementCredit = "Photo: one of SACOP's resettlement communities (sacop.ph)";

return [

    'associated' => [
        [
            'name' => 'San Lorenzo Ruiz Active Peoples Association, Inc. (SAPA, Inc.)',
            'kind' => "People's organization",
            'location' => 'Sitio San Lorenzo, Sapalibutad, Angeles City',
            'connection' => "A people's organization assisted through SACOP's community-organizing program.",
            'source' => 'https://sacop.ph/2024/10/07/ing-mayap-balita-amare-et-servire-october-2024/',
            'photo' => $cdn('i2', '2024/10/1.jpg'),
            'credit' => 'Photo: SACOP newsletter, October 2024 (sacop.ph)',
        ],
        [
            'name' => 'Promised Land Resettlement Community',
            'kind' => 'Resettlement community',
            'location' => 'Calibutbut, Bacolor, Pampanga',
            'connection' => 'Developed and managed by SACOP for families affected by displacement and disasters.',
            'source' => $resettlements,
            'photo' => $cdn('i1', '2017/09/Res-1.jpg'),
            'credit' => $resettlementCredit,
        ],
        [
            'name' => 'Mary Land Resettlement Community',
            'kind' => 'Resettlement community',
            'location' => 'Sapang Biabas, Mabalacat, Pampanga',
            'connection' => 'A resettlement community developed and managed by SACOP.',
            'source' => $resettlements,
            'photo' => $cdn('i0', '2017/09/Res-d.jpg'),
            'credit' => $resettlementCredit,
        ],
        [
            'name' => 'Wenceslao Village',
            'kind' => 'Resettlement community',
            'location' => 'San Roque Dau I, Lubao, Pampanga',
            'connection' => 'A resettlement community developed and managed by SACOP.',
            'source' => $resettlements,
            'photo' => $cdn('i0', '2017/09/Res-a.jpg'),
            'credit' => $resettlementCredit,
        ],
        [
            'name' => 'Buensuceso Ville',
            'kind' => 'Resettlement community',
            'location' => 'Buensuceso, Arayat, Pampanga',
            'connection' => 'A resettlement community administered by SACOP.',
            'source' => $resettlements,
            'photo' => $cdn('i1', '2017/09/Res-c.jpg'),
            'credit' => $resettlementCredit,
        ],
        [
            'name' => 'Villa Arenas',
            'kind' => 'Resettlement community',
            'location' => 'Arenas, Arayat, Pampanga',
            'connection' => 'A resettlement community administered by SACOP.',
            'source' => $resettlements,
            'photo' => $cdn('i0', '2017/09/Res-b.jpg'),
            'credit' => $resettlementCredit,
        ],
        [
            'name' => 'SACOP Benedict XVI Homes',
            'kind' => 'Housing project',
            'location' => 'San Agustin–San Pablo, Magalang, Pampanga',
            'connection' => 'A SACOP housing project for qualified low-income households.',
            'source' => 'https://sacop.ph/housing/',
            'photo' => $cdn('i0', '2017/09/Housing-G1.png'),
            'credit' => "Photo: SACOP's Housing Development Program (sacop.ph)",
        ],
        [
            'name' => 'San Jose Parish',
            'kind' => 'Parish',
            'location' => 'San Jose, City of San Fernando, Pampanga',
            'connection' => 'Supported a SACOP medical mission in Sitio Matahimik.',
            'source' => 'https://sacop.ph/2025/05/08/ing-mayap-balita-amare-et-servire-april-2025/',
            'photo' => $cdn('i0', '2025/05/3.jpg'),
            'credit' => 'Photo: SACOP medical mission, April 2025 newsletter (sacop.ph)',
        ],
        [
            'name' => 'Sto. Domingo Parish',
            'kind' => 'Parish',
            'location' => 'Sto. Domingo, Mexico, Pampanga',
            'connection' => "Associated with a SACOP volunteer featured in SACOP's newsletter.",
            'source' => 'https://sacop.ph/2025/02/18/ing-mayap-balita-amare-et-servire-february-2025/',
            'photo' => 'images/associated/sto-domingo-parish.webp',
            'alt' => 'Sto. Domingo Parish church and bell tower',
            'credit' => null,
        ],
        [
            'name' => "The Lord's Annunciation Parish",
            'kind' => 'Parish',
            'location' => null,
            'connection' => "Represented on SACOP's Board of Trustees through its parish priest.",
            'source' => $about,
            'photo' => 'images/associated/lords-annunciation-parish.webp',
            'alt' => "The Lord's Annunciation Parish church",
            'credit' => null,
        ],
        [
            'name' => 'Nuestra Señora del Carmen Parish',
            'kind' => 'Parish',
            'location' => null,
            'connection' => "Represented on SACOP's Board of Trustees through its parish priest.",
            'source' => $about,
            'photo' => 'images/associated/nuestra-senora-del-carmen-parish.webp',
            'alt' => 'The altar of Nuestra Señora del Carmen Parish',
            'credit' => 'Photo: TheTravelLhemz',
        ],
        [
            'name' => "The Lord's Transfiguration Parish",
            'kind' => 'Parish',
            'location' => null,
            'connection' => "Represented on SACOP's Board of Trustees through its parish priest.",
            'source' => $about,
            'photo' => 'images/associated/lords-transfiguration-parish.webp',
            'alt' => "The Lord's Transfiguration Parish church",
            'credit' => 'Photo: richardbuan.com',
        ],
    ],

    // The people SACOP's programs reach (not organizations, so they are not in the request form).
    'serves' => [
        'Indigenous communities' => 'Communities that may have limited access to healthcare and other basic services.',
        'Poor and marginalized families' => 'Families who need humanitarian assistance and support for their basic needs.',
        'Students from disadvantaged families' => "Students who qualify for educational assistance and scholarships through SACOP's programs.",
        'Families affected by disasters' => 'Communities needing emergency relief, food supplies, and rehabilitation assistance.',
    ],
];
