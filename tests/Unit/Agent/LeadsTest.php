<?php

namespace {
    // Stand-ins so this test also runs without the framework installed (real Laravel provides both).
    if (! function_exists('config')) {
        function config($k = null, $d = null) { return $d; }
    }
    if (! interface_exists('Carbon\\CarbonInterface')) {
        eval('namespace Carbon; interface CarbonInterface { public function gt($d): bool; }
              class Carbon implements CarbonInterface { public function __construct(public int $t) {}
              public static function parse(string $s): self { return new self(strtotime($s)); }
              public function gt($d): bool { return $this->t > $d->t; } }');
    }
}

namespace Tests\Unit\Agent {

use App\Services\Agent\Leads\LeadCsv;
use App\Services\Agent\Leads\LeadImporter;
use App\Services\Agent\Leads\LeadNormalizer;
use App\Services\Agent\Leads\Phone;
use App\Services\Agent\Leads\SequencePlanner;
use App\Services\Agent\Leads\Templates;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LeadsTest extends TestCase
{
    public function testPhoneNormalisation(): void
    {
        $this->assertSame('+2348031234567', Phone::normalize('0803 123 4567', '234'));
        $this->assertSame('+2348031234567', Phone::normalize('+234 803 123 4567', '234'));
        $this->assertSame('+2348031234567', Phone::normalize('00234-803-123-4567', '234'));
        $this->assertNull(Phone::normalize('abc', '234'));
        $this->assertNull(Phone::normalize('123', '234'));
        $this->assertSame('https://wa.me/2348031234567?text=Hi%20there', Phone::waLink('+2348031234567', 'Hi there'));
    }

    public function testCsvTemplateRoundTrips(): void
    {
        $rows = LeadCsv::parse(LeadCsv::template());
        $this->assertCount(2, $rows);
        $this->assertSame('Bright Hair Studio', $rows[0]['company']);
        $this->assertSame('ada@brighthair.example', $rows[0]['email']);
        $this->assertSame('Okafor Motors', $rows[1]['company']);
        $this->assertSame('', $rows[1]['email']);
    }

    public function testCsvHeaderAliasesAndSemicolons(): void
    {
        $rows = LeadCsv::parse("Business Name;Mobile;E-mail\nAcme Ltd;0803 000 1111;a@acme.test\n");
        $this->assertCount(1, $rows);
        $this->assertSame('Acme Ltd', $rows[0]['company']);
        $this->assertSame('0803 000 1111', $rows[0]['phone']);
        $this->assertSame('a@acme.test', $rows[0]['email']);
    }

    public function testRawTextIsNotTreatedAsCsv(): void
    {
        $this->assertSame([], LeadCsv::parse("Met Tola at the expo, she runs a bakery\n0803 111 2222"));
    }

    public function testNormalizerDropsInventedContacts(): void
    {
        $raw = 'Tola Bakes, tola@bakes.test, 0803 111 2222';
        $rows = LeadNormalizer::clean([
            ['company' => 'Tola Bakes', 'email' => 'tola@bakes.test', 'phone' => '0803 111 2222'],
            ['company' => 'Made Up Co', 'email' => 'ceo@madeup.test', 'phone' => '0909 999 9999'],
            ['contact_name' => 'Only Person'],
            ['foo' => 'bar'],
        ], $raw);
        $this->assertCount(3, $rows);
        $this->assertSame('tola@bakes.test', $rows[0]['email']);
        $this->assertSame('', $rows[1]['email']);
        $this->assertSame('', $rows[1]['phone']);
        $this->assertSame('Only Person', $rows[2]['company']);
    }

    public function testRowProblems(): void
    {
        $this->assertSame([], LeadImporter::problems(['company' => 'A', 'email' => 'a@b.co', 'phone' => '']));
        $this->assertContains('No company name', LeadImporter::problems(['company' => '', 'email' => 'a@b.co']));
        $this->assertContains('Email looks invalid', LeadImporter::problems(['company' => 'A', 'email' => 'nope', 'phone' => '0803 111 2222']));
    }

    public function testTemplateRendering(): void
    {
        $out = Templates::render("Hi {name},\n\n{documents}\n\nBye", ['name' => 'Ada', 'documents' => '']);
        $this->assertSame("Hi Ada,\n\nBye", preg_replace("/\n{3,}/", "\n\n", $out));
        $this->assertSame('Hi Ada, about Acme', Templates::render('Hi {name}, about {company}', ['name' => 'Ada', 'company' => 'Acme']));
    }

    private function state(array $o = []): array
    {
        return $o + ['mode' => 'email', 'step' => 0, 'total' => 3, 'reminders' => 0, 'next_action_at' => Carbon::parse('2026-11-08 09:00'),
            'has_phone' => false, 'has_email' => true, 'auto_on' => true, 'in_window' => true, 'cap_left' => 5];
    }

    public function testPlannerWaitsUntilDue(): void
    {
        $now = Carbon::parse('2026-11-08 08:00');
        $this->assertSame(SequencePlanner::WAIT, SequencePlanner::decide($this->state(), $now));
    }

    public function testPlannerSendsOrNotifiesForEmailSteps(): void
    {
        $now = Carbon::parse('2026-11-08 10:00');
        $this->assertSame(SequencePlanner::SEND_EMAIL, SequencePlanner::decide($this->state(), $now));
        $this->assertSame(SequencePlanner::NOTIFY_EMAIL, SequencePlanner::decide($this->state(['auto_on' => false]), $now));
        $this->assertSame(SequencePlanner::WAIT, SequencePlanner::decide($this->state(['in_window' => false]), $now));
        $this->assertSame(SequencePlanner::WAIT, SequencePlanner::decide($this->state(['cap_left' => 0]), $now));
    }

    public function testPlannerMovesToPhoneOrClosesAfterEmails(): void
    {
        $now = Carbon::parse('2026-11-08 10:00');
        $this->assertSame(SequencePlanner::SWITCH_TO_PHONE, SequencePlanner::decide($this->state(['step' => 3, 'has_phone' => true]), $now));
        $this->assertSame(SequencePlanner::FINISH, SequencePlanner::decide($this->state(['step' => 3, 'has_phone' => false]), $now));
        $this->assertSame(SequencePlanner::SWITCH_TO_PHONE, SequencePlanner::decide($this->state(['has_email' => false, 'has_phone' => true]), $now));
        $this->assertSame(SequencePlanner::FINISH, SequencePlanner::decide($this->state(['has_email' => false]), $now));
    }

    public function testPlannerPhoneRemindersThenClose(): void
    {
        $now = Carbon::parse('2026-11-08 10:00');
        $m = ['mode' => 'manual', 'total' => 1, 'has_phone' => true, 'has_email' => false];
        $this->assertSame(SequencePlanner::NOTIFY_PHONE, SequencePlanner::decide($this->state($m), $now));
        $this->assertSame(SequencePlanner::NOTIFY_PHONE, SequencePlanner::decide($this->state($m + ['reminders' => 2]), $now));
        $this->assertSame(SequencePlanner::FINISH, SequencePlanner::decide($this->state($m + ['reminders' => 3]), $now));
        $this->assertSame(SequencePlanner::FINISH, SequencePlanner::decide($this->state($m + ['step' => 1]), $now));
    }

    public function testEmailDelays(): void
    {
        $this->assertSame(3, SequencePlanner::emailDelayAfter(1, [0, 3, 4], 4));
        $this->assertSame(4, SequencePlanner::emailDelayAfter(2, [0, 3, 4], 9));
        $this->assertSame(9, SequencePlanner::emailDelayAfter(3, [0, 3, 4], 9));
    }
}

}
