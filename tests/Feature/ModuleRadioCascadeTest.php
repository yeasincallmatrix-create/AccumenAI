<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /admin/module-config — parent → children radio cascade.
 *
 * Rule 1: changing a parent radio cascades its category to every child.
 * Rule 2: a child can still be set individually afterwards.
 * Rule 3: a parent change wipes those child overrides.
 *
 * The cascade itself is client-side (index.blade.php script); these tests
 * pin the markup contract the script binds to and the server-side round trip
 * of a cascaded payload.
 */
class ModuleRadioCascadeTest extends TestCase
{
    use DatabaseTransactions;

    private function platformAdmin(): PlatformAdmin
    {
        TenantContext::clear();

        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'platform-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function matrixHtml(): string
    {
        return $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->getContent();
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $dom;
    }

    private function xpath(string $html): \DOMXPath
    {
        return new \DOMXPath($this->dom($html));
    }

    /**
     * @param  array<string, array{module_key: string, category: string}>  $modules
     */
    private function saveMatrix(array $modules)
    {
        return $this->actingAs($this->platformAdmin(), 'platform_admin')->put(
            route('admin.module-config.update'),
            [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
                'modules' => $modules,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function parentKeysWithChildren(): array
    {
        return DB::table('module_registry')
            ->whereNotNull('parent_key')
            ->distinct()
            ->orderBy('parent_key')
            ->pluck('parent_key')
            ->all();
    }

    public function test_parent_radios_carry_data_parent_key(): void
    {
        $xpath = $this->xpath($this->matrixHtml());
        $parents = $this->parentKeysWithChildren();

        $nodes = $xpath->query('//input[@type="radio" and contains(concat(" ", normalize-space(@class), " "), " parent-radio ")]');

        $this->assertSame(count($parents) * 4, $nodes->length,
            'Every collapsible parent must expose 4 parent-radio inputs (one per bucket)');

        foreach ($nodes as $node) {
            $parentKey = $node->getAttribute('data-parent-key');
            $this->assertNotSame('', $parentKey, 'parent-radio is missing data-parent-key');
            $this->assertContains($parentKey, $parents, "{$parentKey} is not a registered parent with children");
        }
    }

    public function test_child_radios_reference_their_parent(): void
    {
        $xpath = $this->xpath($this->matrixHtml());

        $nodes = $xpath->query('//input[@type="radio" and contains(concat(" ", normalize-space(@class), " "), " child-radio ")]');

        $childTotal = (int) DB::table('module_registry')->whereNotNull('parent_key')->count();

        $this->assertSame($childTotal * 4, $nodes->length,
            'Every child must expose 4 child-radio inputs (one per bucket)');

        $seen = [];
        foreach ($nodes as $node) {
            $childKey = $node->getAttribute('data-child-key');
            $parentKey = $node->getAttribute('data-parent-key');

            $this->assertNotSame('', $childKey, 'child-radio is missing data-child-key');
            $this->assertNotSame('', $parentKey, 'child-radio is missing data-parent-key');
            $this->assertSame(
                explode('.', $childKey)[0],
                $parentKey,
                "child-radio {$childKey} must reference parent {$parentKey}"
            );
            $seen[$childKey] = true;
        }

        $this->assertCount($childTotal, $seen, 'Every registered child must render radios');
    }

    public function test_every_parent_child_pair_is_cascadable(): void
    {
        $xpath = $this->xpath($this->matrixHtml());

        foreach ($this->parentKeysWithChildren() as $parentKey) {
            $parentRadios = $xpath->query(
                '//input[@type="radio" and @data-parent-key="'.$parentKey.'" and contains(concat(" ", normalize-space(@class), " "), " parent-radio ")]'
            )->length;

            $childRadios = $xpath->query(
                '//input[@type="radio" and @data-parent-key="'.$parentKey.'" and contains(concat(" ", normalize-space(@class), " "), " child-radio ")]'
            )->length;

            $children = (int) DB::table('module_registry')->where('parent_key', $parentKey)->count();

            $this->assertSame(4, $parentRadios, "Parent {$parentKey} must expose 4 radios");
            $this->assertSame($children * 4, $childRadios,
                "All {$children} children of {$parentKey} must be reachable by the cascade selector");
        }
    }

    public function test_child_rows_carry_data_child_key_and_toggle_hook(): void
    {
        $html = $this->matrixHtml();
        $xpath = $this->xpath($html);

        $childRows = $xpath->query('//tr[@data-child-key and @data-child-of]');

        $this->assertGreaterThan(0, $childRows->length);

        foreach ($childRows as $row) {
            $this->assertSame($row->getAttribute('data-child-of'), explode('.', $row->getAttribute('data-child-key'))[0]);
            $this->assertStringContainsString('module-child-row', $row->getAttribute('class'));
        }

        $this->assertStringContainsString('data-module-toggle="accounting"', $html,
            'The existing parent collapse hook must survive the cascade change');
    }

    public function test_cascade_script_and_override_styles_are_rendered(): void
    {
        $html = $this->matrixHtml();

        $this->assertStringContainsString('.parent-radio', $html, 'Parent cascade listener missing');
        $this->assertStringContainsString('.child-radio', $html, 'Child override listener missing');
        $this->assertStringContainsString('data-parent-key="\' + parentKey + \'"', $html, 'Cascade selector missing');
        $this->assertStringContainsString('child-override', $html, 'Override marker missing');
        $this->assertStringContainsString('.child-row.child-override', $html, 'Override stylesheet missing');
        $this->assertStringContainsString('cascadeToast', $html, 'Toast feedback missing');
        $this->assertStringContainsString('Rule 3', $html, 'Override reset on parent change missing');
    }

    public function test_parent_change_cascades_children_to_same_category(): void
    {
        $accountingChildren = DB::table('module_registry')
            ->where('parent_key', 'accounting')
            ->pluck('key')
            ->all();

        $this->assertCount(28, $accountingChildren);

        // Exactly what the browser sends after Rule 1 fires: parent + every child.
        $payload = ['row0' => ['module_key' => 'accounting', 'category' => 'hidden']];
        foreach ($accountingChildren as $i => $key) {
            $payload['row'.($i + 1)] = ['module_key' => $key, 'category' => 'hidden'];
        }

        $this->saveMatrix($payload)->assertRedirect(route('admin.module-config.index', [
            'industry' => 'healthcare',
            'subcategory' => 'pharmacy',
        ]));

        $subcategoryId = (int) DB::table('industry_subcategories')
            ->where('industry_key', 'healthcare')
            ->where('subcategory_key', 'pharmacy')
            ->value('id');

        $hidden = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $subcategoryId)
            ->whereIn('module_key', array_merge(['accounting'], $accountingChildren))
            ->where('category', 'hidden')
            ->count();

        $this->assertSame(count($accountingChildren) + 1, $hidden,
            'Parent + all 28 children must persist as hidden after a cascade');

        // Rendered state: no accounting child may be flagged as an override.
        $xpath = $this->xpath($this->matrixHtml());
        $overrides = $xpath->query('//tr[@data-child-of="accounting" and contains(concat(" ", normalize-space(@class), " "), " child-override ")]');

        $this->assertSame(0, $overrides->length, 'A fully cascaded parent must render zero overrides');
    }

    public function test_child_override_is_persisted_and_marked_on_render(): void
    {
        $this->saveMatrix([
            'row0' => ['module_key' => 'accounting', 'category' => 'default'],
            'row1' => ['module_key' => 'accounting.invoices', 'category' => 'mandatory'],
            'row2' => ['module_key' => 'accounting.bills', 'category' => 'default'],
        ])->assertRedirect(route('admin.module-config.index', [
            'industry' => 'healthcare',
            'subcategory' => 'pharmacy',
        ]));

        $subcategoryId = (int) DB::table('industry_subcategories')
            ->where('industry_key', 'healthcare')
            ->where('subcategory_key', 'pharmacy')
            ->value('id');

        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $subcategoryId,
            'module_key' => 'accounting',
            'category' => 'default',
        ]);
        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $subcategoryId,
            'module_key' => 'accounting.invoices',
            'category' => 'mandatory',
        ]);

        $xpath = $this->xpath($this->matrixHtml());

        $overridden = $xpath->query('//tr[@data-child-key="accounting.invoices" and contains(concat(" ", normalize-space(@class), " "), " child-override ")]');
        $this->assertSame(1, $overridden->length,
            'A child that differs from its parent must render with the override marker');

        $inSync = $xpath->query('//tr[@data-child-key="accounting.bills" and contains(concat(" ", normalize-space(@class), " "), " child-override ")]');
        $this->assertSame(0, $inSync->length,
            'A child that matches its parent must not be flagged as an override');

        $parentRow = $xpath->query('//tr[@data-parent-key="accounting"]');
        $this->assertSame(1, $parentRow->length, 'Parent row must carry data-parent-key');

        $this->assertStringContainsString('override', $xpath->query('//tr[@data-child-key="accounting.invoices"]')->item(0)->textContent);
    }

    public function test_single_rows_stay_out_of_the_cascade(): void
    {
        $xpath = $this->xpath($this->matrixHtml());

        $this->assertSame(
            0,
            $xpath->query('//tr[@data-parent-key="notifications"]')->length,
            'Childless modules must not become cascade parents'
        );
        $this->assertSame(
            0,
            $xpath->query('//input[@type="radio" and @data-parent-key="notifications"]')->length,
            'Childless modules must not expose cascade radios'
        );
    }
}
