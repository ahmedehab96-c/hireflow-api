<?php

namespace Tests\Feature;

use App\Models\Cv;
use App\Models\User;
use App\Services\AI\Exceptions\AiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakesAi;
use Tests\Support\Pdf;
use Tests\TestCase;

class CvTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->candidate = User::factory()->create();
        Sanctum::actingAs($this->candidate);
    }

    private function cvPdf(string $name = 'my-cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, Pdf::withText([
            'Jane Example - Senior Backend Engineer',
            'Experience: 6 years building Laravel and MySQL platforms in Dubai.',
            'Skills: PHP, Laravel, MySQL, Docker, AWS, REST APIs',
        ]));
    }

    private function analysis(int $score = 78): array
    {
        return [
            'score' => $score,
            'summary' => 'Experienced backend engineer.',
            'skills' => ['PHP', 'Laravel', 'php', ' MySQL '],
            'experience' => [['role' => 'Backend Engineer', 'organization' => 'Example Co', 'period' => '2020 - now', 'highlights' => 'Built APIs']],
            'education' => [['qualification' => 'BSc Computer Science', 'institution' => 'Example University', 'period' => '2016']],
            'strengths' => ['Clear structure'],
            'weaknesses' => ['Few metrics'],
            'missing_keywords' => ['CI/CD'],
            'recommendations' => ['Quantify achievements'],
        ];
    }

    public function test_candidate_can_upload_pdf(): void
    {
        $response = $this->post('/api/cvs', ['file' => $this->cvPdf()]);

        $response->assertCreated()
            ->assertJsonPath('data.original_filename', 'my-cv.pdf')
            ->assertJsonPath('data.is_analyzed', false)
            ->assertJsonPath('data.score', null)
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.extracted_text');

        $cv = Cv::firstOrFail();
        $this->assertSame($this->candidate->id, $cv->user_id);
        $this->assertStringContainsString('Senior Backend Engineer', $cv->extracted_text);
        Storage::disk('local')->assertExists($cv->file_path);
    }

    public function test_invalid_files_are_rejected(): void
    {
        $this->post('/api/cvs', [])->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->post('/api/cvs', ['file' => UploadedFile::fake()->create('cv.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);

        // Renamed text file: extension says PDF but the content does not.
        $this->post('/api/cvs', ['file' => UploadedFile::fake()->createWithContent('fake.pdf', 'just some plain text pretending to be a pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->post('/api/cvs', ['file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('cvs', 0);
    }

    public function test_pdf_without_text_returns_clear_error_and_stores_nothing(): void
    {
        $this->post('/api/cvs', ['file' => UploadedFile::fake()->createWithContent('scan.pdf', Pdf::blank())])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('cvs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_candidate_sees_only_own_cvs(): void
    {
        Cv::factory()->count(2)->for($this->candidate)->create();
        Cv::factory()->count(3)->create();

        $this->getJson('/api/cvs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.analysis');
    }

    public function test_candidate_cannot_access_another_candidates_cv(): void
    {
        $other = Cv::factory()->analyzed()->create();

        $this->getJson("/api/cvs/{$other->id}")->assertNotFound();
        $this->deleteJson("/api/cvs/{$other->id}")->assertNotFound();

        $this->assertModelExists($other);
    }

    public function test_candidate_can_view_and_delete_own_cv(): void
    {
        Storage::disk('local')->put('cvs/test/mine.pdf', 'x');
        $cv = Cv::factory()->for($this->candidate)->analyzed(81)->create(['file_path' => 'cvs/test/mine.pdf']);

        $this->getJson("/api/cvs/{$cv->id}")->assertOk()->assertJsonPath('data.score', 81)->assertJsonPath('data.analysis.skills.0', 'PHP');

        $this->deleteJson("/api/cvs/{$cv->id}")->assertOk();
        $this->assertModelMissing($cv);
        Storage::disk('local')->assertMissing('cvs/test/mine.pdf');
    }

    public function test_candidate_can_analyze_own_cv(): void
    {
        $ai = $this->fakeAi($this->analysis(78));
        $cv = Cv::factory()->for($this->candidate)->create();

        $this->postJson("/api/cvs/{$cv->id}/analyze")
            ->assertOk()
            ->assertJsonPath('data.score', 78)
            ->assertJsonPath('data.is_analyzed', true)
            ->assertJsonPath('data.analysis.summary', 'Experienced backend engineer.')
            // Normalised: trimmed and de-duplicated case-insensitively.
            ->assertJsonPath('data.analysis.skills', ['PHP', 'Laravel', 'MySQL']);

        $this->assertSame(78, $cv->fresh()->score);
        $this->assertStringContainsString($cv->extracted_text, $ai->calls[0]['prompt']);
    }

    public function test_unauthorized_analysis_is_rejected(): void
    {
        $ai = $this->fakeAi($this->analysis());
        $other = Cv::factory()->create();

        $this->postJson("/api/cvs/{$other->id}/analyze")->assertNotFound();
        $this->assertNull($other->fresh()->analysis_json);
        $this->assertSame([], $ai->calls);

        // Guests and admins can't reach CV endpoints at all.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/cvs/{$other->id}/analyze")->assertForbidden();
        $this->getJson('/api/cvs')->assertForbidden();
    }

    public function test_guest_cannot_use_cv_endpoints(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();

        $this->withHeaders(['Authorization' => ''])->getJson('/api/cvs')->assertUnauthorized();
    }

    public function test_ai_failure_is_handled_gracefully_and_keeps_previous_analysis(): void
    {
        $this->fakeAi(new AiException);
        $cv = Cv::factory()->for($this->candidate)->analyzed(64)->create();

        $this->postJson("/api/cvs/{$cv->id}/analyze")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The AI service could not complete this request. Please try again in a moment.');

        $this->assertSame(64, $cv->fresh()->score);
    }

    public function test_missing_ai_configuration_returns_useful_message_without_leaking_secrets(): void
    {
        config(['ai.provider' => 'anthropic', 'ai.api_key' => null]);
        $cv = Cv::factory()->for($this->candidate)->create();

        $response = $this->postJson("/api/cvs/{$cv->id}/analyze")->assertStatus(503)->assertJsonPath('success', false);

        $this->assertStringContainsString('not configured', $response->json('message'));
        $this->assertNull($cv->fresh()->analysis_json);
    }

    public function test_invalid_ai_output_is_rejected(): void
    {
        $this->fakeAi(['summary' => 'no score here']);
        $cv = Cv::factory()->for($this->candidate)->create();

        $this->postJson("/api/cvs/{$cv->id}/analyze")->assertStatus(502);
        $this->assertNull($cv->fresh()->score);
    }

    public function test_upload_limit_per_candidate(): void
    {
        config(['cv.max_per_user' => 1]);
        Cv::factory()->for($this->candidate)->create();

        $this->post('/api/cvs', ['file' => $this->cvPdf()])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }
}
