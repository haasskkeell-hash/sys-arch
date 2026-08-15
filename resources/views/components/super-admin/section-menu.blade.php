@props([
    'actions' => [],
    'label' => __('Section actions'),
])

<x-table-actions :actions="$actions" :label="$label" data-super-admin-section-menu />
