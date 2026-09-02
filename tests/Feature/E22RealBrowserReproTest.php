<?php
namespace Tests\Feature;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class E22RealBrowserReproTest extends TestCase
{
    use DatabaseTransactions;
    public function test_repro_approve_and_delete_browser_flow(): void
    {
        $password='BrowserPass123!';
        $admin=PlatformAdmin::firstOrReuseForTests(['email'=>'e22-'.uniqid().'@test.local','password_hash'=>bcrypt($password),'status'=>'active','email_verified_at'=>now()]);
        $pending=Institute::create(['name'=>'E22 Pending '.uniqid(),'slug'=>'e22-'.uniqid(),'status'=>'pending']);
        $active=Institute::create(['name'=>'E22 Active '.uniqid(),'slug'=>'e22a-'.uniqid(),'status'=>'active']);
        // Simulate browser: actingAs and then POST with FormData-like (post with _token handling)
        $this->actingAs($admin,'platform_admin');
        // Capture before
        $beforeApprove=Institute::find($pending->id);
        echo "\n[APPROVE] before status={$beforeApprove->status} onboarded_at=".json_encode($beforeApprove->onboarded_at)."\n";
        // Browser sends POST with FormData, Accept json, X-CSRF
        $response=$this->call('POST', route('admin.institutes.action', $pending), ['action'=>'approve','_token'=>csrf_token()], [], [], ['HTTP_Accept'=>'application/json','HTTP_X-CSRF-TOKEN'=>csrf_token(),'HTTP_X-Requested-With'=>'XMLHttpRequest']);
        echo "[APPROVE] REQUEST URL: ".route('admin.institutes.action',$pending)."\n";
        echo "METHOD: POST\n";
        echo "STATUS: ".$response->getStatusCode()."\n";
        echo "PAYLOAD: action=approve\n";
        echo "RESPONSE: ".$response->getContent()."\n";
        $after=Institute::find($pending->id);
        echo "DB RESULT: status={$after->status} onboarded_at={$after->onboarded_at} deleted_at=".json_encode($after->deleted_at)."\n";
        $response->assertStatus(200)->assertJson(['success'=>true]);

        // Approve duplicate
        $dup=$this->call('POST', route('admin.institutes.action', $pending), ['action'=>'approve','_token'=>csrf_token()], [], [], ['HTTP_Accept'=>'application/json','HTTP_X-CSRF-TOKEN'=>csrf_token()]);
        echo "\n[APPROVE DUPLICATE] STATUS: ".$dup->getStatusCode()." RESPONSE: ".$dup->getContent()."\n";

        // Delete with wrong password
        $this->actingAs($admin,'platform_admin');
        $beforeDel=Institute::find($active->id);
        echo "\n[DELETE WRONG] before status={$beforeDel->status} deleted_at=".json_encode($beforeDel->deleted_at)."\n";
        $delWrong=$this->call('POST', route('admin.institutes.action', $active), ['action'=>'delete','password'=>'wrong','_token'=>csrf_token()], [], [], ['HTTP_Accept'=>'application/json','HTTP_X-CSRF-TOKEN'=>csrf_token()]);
        echo "[DELETE WRONG] REQUEST URL: ".route('admin.institutes.action',$active)."\n";
        echo "METHOD: POST\n";
        echo "STATUS: ".$delWrong->getStatusCode()."\n";
        echo "PAYLOAD: action=delete password=***\n";
        echo "RESPONSE: ".$delWrong->getContent()."\n";
        $afterWrong=Institute::withTrashed()->find($active->id);
        echo "DB RESULT wrong: deleted_at=".json_encode($afterWrong->deleted_at)."\n";
        $delWrong->assertStatus(422);

        // Delete with correct password
        $delOk=$this->call('POST', route('admin.institutes.action', $active), ['action'=>'delete','password'=>$password,'_token'=>csrf_token()], [], [], ['HTTP_Accept'=>'application/json','HTTP_X-CSRF-TOKEN'=>csrf_token()]);
        echo "\n[DELETE OK] STATUS: ".$delOk->getStatusCode()."\n";
        echo "RESPONSE: ".$delOk->getContent()."\n";
        $afterOk=Institute::withTrashed()->find($active->id);
        echo "DB RESULT ok: status={$afterOk->status} deleted_at={$afterOk->deleted_at} deleted_by={$afterOk->deleted_by}\n";
        $delOk->assertStatus(200)->assertJson(['success'=>true]);
        // Verify disappears from active list
        $list=$this->actingAs($admin,'platform_admin')->call('GET', route('admin.institutes.index'), [], [], [], ['HTTP_Accept'=>'text/html']);
        echo "LIST active contains deleted? ".(str_contains($list->getContent(), $active->name)?'YES':'NO')."\n";
        $bin=$this->actingAs($admin,'platform_admin')->call('GET', route('admin.institutes.bin'), [], [], [], ['HTTP_Accept'=>'text/html']);
        echo "BIN contains deleted? ".(str_contains($bin->getContent(), $active->name)?'YES':'NO')."\n";

        // Restore
        $restore=$this->call('POST', route('admin.institutes.restore', $active->id), ['_token'=>csrf_token()], [], [], ['HTTP_Accept'=>'application/json','HTTP_X-CSRF-TOKEN'=>csrf_token()]);
        echo "\n[RESTORE] STATUS: ".$restore->getStatusCode()." RESPONSE: ".$restore->getContent()."\n";
        $afterRestore=Institute::find($active->id);
        echo "DB RESULT restore: status={$afterRestore->status} deleted_at=".json_encode($afterRestore->deleted_at)."\n";
    }
}
