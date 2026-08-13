<?php

// Dipakai LengthAwarePaginator untuk mengisi meta.links[].label di respons
// list (lihat PLAN-INTEGRASI-FRONTEND.md §4/P2 -- akar masalah sama dengan
// lang/id/validation.php). Tanpa &laquo;/&raquo; bawaan Laravel karena ini
// dikonsumsi sebagai JSON API, bukan dirender langsung sebagai HTML.
return [

    'previous' => 'Sebelumnya',
    'next' => 'Berikutnya',

];
