<?php
DB::table('module_registry')
    ->whereIn('key', ['healthcare', 'medical', 'education', 'training_center', 'retail', 'manufacturing', 'real_estate'])
    ->orWhereIn('parent_key', ['medical', 'education', 'training_center'])
    ->orderBy('parent_key')
    ->orderBy('key')
    ->get()
    ->each(fn($m) => print(($m->parent_key ?? 'ROOT') . ' → ' . $m->key . ' | ' . $m->name . ' | type: ' . $m->type . ' | status: ' . $m->status . PHP_EOL));
echo 'TOTAL: ' . DB::table('module_registry')->whereIn('key', ['healthcare', 'medical', 'education', 'training_center', 'retail', 'manufacturing', 'real_estate'])->orWhereIn('parent_key', ['medical', 'education', 'training_center'])->count() . PHP_EOL;
