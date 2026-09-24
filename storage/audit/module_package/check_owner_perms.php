<?php
$owner = App\Models\Role::where('slug', 'institute-owner')->orWhere('name', 'owner')->orWhere('slug', 'owner')->first();
if (!$owner) {
    echo "NO OWNER ROLE\n";
    $roles = App\Models\Role::pluck('slug', 'id');
    foreach ($roles as $id => $slug) { echo " role #$id $slug\n"; }
    return;
}
echo "Owner role: id={$owner->id} slug={$owner->slug} name={$owner->name}\n";
echo "Permission count: ".$owner->permissions()->count()."\n";
$slugs = $owner->permissions()->pluck('slug')->sort()->values();
echo "All slugs (".count($slugs)."):\n";
foreach ($slugs as $s) { echo "  $s\n"; }
echo "\npurchase%:\n";
foreach ($slugs->filter(fn ($s) => str_starts_with($s, 'purchase')) as $s) { echo "  $s\n"; }
echo "\nsales%:\n";
foreach ($slugs->filter(fn ($s) => str_starts_with($s, 'sales')) as $s) { echo "  $s\n"; }

echo "\n--- Roles summary ---\n";
foreach (App\Models\Role::withCount('permissions')->get() as $r) {
    echo "  {$r->slug} ({$r->name}): {$r->permissions_count} perms\n";
}
