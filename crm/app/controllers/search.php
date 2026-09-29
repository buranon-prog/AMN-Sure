<?php

function c_search_index(): void
{
    $q = (string) get('q', '');
    render('search/index', ['title' => 'ค้นหา', 'q' => $q, 'results' => $q !== '' ? global_search($q) : []]);
}
