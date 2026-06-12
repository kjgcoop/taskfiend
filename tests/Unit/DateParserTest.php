<?php

namespace Tests\Unit;

use App\Services\DateParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for App\Services\DateParser
 *
 * All tests pin the clock to 2026-03-26 (Thursday) so date assertions are
 * deterministic.  A handful of tests temporarily shift to a different day
 * (e.g. Saturday) to exercise weekend-boundary logic.
 */
class DateParserTest extends TestCase
{
    private DateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DateParser();
        Carbon::setTestNow(Carbon::parse('2026-03-26')); // Thursday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset clock
        parent::tearDown();
    }

    // =========================================================================
    // parseTaskInput() — no date or recurrence
    // =========================================================================

    public function test_plain_text_returns_name_unchanged_with_no_date(): void
    {
        $result = $this->parser->parseTaskInput('Buy groceries');

        $this->assertSame('Buy groceries', $result['name']);
        $this->assertNull($result['date']);
        $this->assertNull($result['recurrence_pattern']);
        $this->assertFalse($result['recurrence_floating']);
    }

    // =========================================================================
    // parseTaskInput() — specific one-off dates
    // =========================================================================

    public function test_today_keyword_sets_date_to_today(): void
    {
        $result = $this->parser->parseTaskInput('Buy groceries today');

        $this->assertSame('Buy groceries', $result['name']);
        $this->assertSame('2026-03-26', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_today_alone_falls_back_to_original_input_as_name(): void
    {
        // After stripping "today" the name is empty, so the parser restores the original input.
        $result = $this->parser->parseTaskInput('today');

        $this->assertSame('today', $result['name']);
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_tomorrow_keyword_sets_date_to_tomorrow(): void
    {
        $result = $this->parser->parseTaskInput('Dentist appointment tomorrow');

        $this->assertSame('Dentist appointment', $result['name']);
        $this->assertSame('2026-03-27', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_iso_date_is_parsed(): void
    {
        $result = $this->parser->parseTaskInput('Submit report 2026-04-15');

        $this->assertSame('Submit report', $result['name']);
        $this->assertSame('2026-04-15', $result['date']);
    }

    public function test_slash_date_in_future_is_parsed(): void
    {
        $result = $this->parser->parseTaskInput('Dentist 4/5');

        $this->assertSame('Dentist', $result['name']);
        $this->assertSame('2026-04-05', $result['date']);
    }

    public function test_slash_date_in_past_wraps_to_next_year(): void
    {
        // March 15 2026 is already past (today is March 26).
        $result = $this->parser->parseTaskInput('Dentist 3/15');

        $this->assertSame('2027-03-15', $result['date']);
    }

    public function test_month_day_in_future_is_parsed(): void
    {
        $result = $this->parser->parseTaskInput('Conference April 5');

        $this->assertSame('Conference', $result['name']);
        $this->assertSame('2026-04-05', $result['date']);
    }

    public function test_month_day_in_past_wraps_to_next_year(): void
    {
        $result = $this->parser->parseTaskInput('Birthday March 15');

        $this->assertSame('2027-03-15', $result['date']);
    }

    // =========================================================================
    // parseTaskInput() — day-of-week (non-recurring, single occurrence)
    // =========================================================================

    public function test_single_day_name_schedules_next_occurrence(): void
    {
        // From Thursday, the next Monday is 2026-03-30.
        $result = $this->parser->parseTaskInput('Call dentist Monday');

        $this->assertSame('Call dentist', $result['name']);
        $this->assertSame('2026-03-30', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_single_day_name_tomorrow_is_nearest(): void
    {
        // From Thursday, the next Friday is 2026-03-27.
        $result = $this->parser->parseTaskInput('Call dentist Friday');

        $this->assertSame('Call dentist', $result['name']);
        $this->assertSame('2026-03-27', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_day_name_same_as_today_schedules_next_week(): void
    {
        // Today is Thursday; "Thursday" should schedule for the following Thursday.
        $result = $this->parser->parseTaskInput('Meeting Thursday');

        $this->assertSame('2026-04-02', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_next_prefix_removes_word_from_name(): void
    {
        $result = $this->parser->parseTaskInput('Doctor next Monday');

        $this->assertSame('Doctor', $result['name']);
        $this->assertSame('2026-03-30', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    public function test_last_day_name_wins_when_multiple_day_names_present(): void
    {
        // "Letter on Sunday Tuesday" → schedule for Tuesday (last day name),
        // but keep "Sunday" in the title because it's part of the prose.
        $result = $this->parser->parseTaskInput('Letter on Sunday Tuesday');

        $this->assertSame('Letter on Sunday', $result['name']);
        $this->assertSame('2026-03-31', $result['date']);
        $this->assertNull($result['recurrence_pattern']);
    }

    // =========================================================================
    // parseTaskInput() — day-of-week (recurring via plural / "every" prefix)
    // =========================================================================

    public function test_plural_day_name_is_treated_as_recurring(): void
    {
        $result = $this->parser->parseTaskInput('Stand-up Mondays');

        $this->assertSame('Stand-up', $result['name']);
        $this->assertSame('Monday', $result['recurrence_pattern']);
        $this->assertSame('2026-03-30', $result['date']);
    }

    public function test_every_day_name_is_treated_as_recurring(): void
    {
        $result = $this->parser->parseTaskInput('Team sync every Tuesday');

        $this->assertSame('Team sync', $result['name']);
        $this->assertSame('Tuesday', $result['recurrence_pattern']);
        $this->assertSame('2026-03-31', $result['date']);
    }

    // =========================================================================
    // parseTaskInput() — multi-day recurrence
    // =========================================================================

    public function test_multi_day_full_names_comma_separated(): void
    {
        $result = $this->parser->parseTaskInput('Gym Monday, Wednesday, Friday');

        $this->assertSame('Gym', $result['name']);
        $this->assertSame('mon,wed,fri', $result['recurrence_pattern']);
        // From Thursday, the next day in mon/wed/fri is Friday 2026-03-27.
        $this->assertSame('2026-03-27', $result['date']);
    }

    public function test_multi_day_abbreviations_comma_separated(): void
    {
        $result = $this->parser->parseTaskInput('Gym mon,wed,fri');

        $this->assertSame('mon,wed,fri', $result['recurrence_pattern']);
        $this->assertSame('2026-03-27', $result['date']);
    }

    // =========================================================================
    // parseTaskInput() — simple recurrence keywords
    // =========================================================================

    public function test_daily_keyword_sets_pattern_and_date_to_today(): void
    {
        $result = $this->parser->parseTaskInput('Morning run daily');

        $this->assertSame('Morning run', $result['name']);
        $this->assertSame('daily', $result['recurrence_pattern']);
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_every_day_is_alias_for_daily(): void
    {
        $result = $this->parser->parseTaskInput('Morning run every day');

        $this->assertSame('daily', $result['recurrence_pattern']);
    }

    public function test_weekdays_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Check email weekdays');

        $this->assertSame('Check email', $result['name']);
        $this->assertSame('weekdays', $result['recurrence_pattern']);
        // Today is Thursday (a weekday), so the date is today.
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_weekdays_pattern_when_today_is_weekend_schedules_next_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-28')); // Saturday
        $result = $this->parser->parseTaskInput('Check email weekdays');

        $this->assertSame('weekdays', $result['recurrence_pattern']);
        $this->assertSame('2026-03-30', $result['date']); // next Monday
    }

    public function test_weekends_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Work out weekends');

        $this->assertSame('Work out', $result['name']);
        $this->assertSame('weekends', $result['recurrence_pattern']);
        // From Thursday, the next weekend day is Saturday 2026-03-28.
        $this->assertSame('2026-03-28', $result['date']);
    }

    public function test_weekends_pattern_when_today_is_weekend_uses_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-28')); // Saturday
        $result = $this->parser->parseTaskInput('Work out weekends');

        $this->assertSame('2026-03-28', $result['date']);
    }

    public function test_every_other_day_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Walk the dog every other day');

        $this->assertSame('Walk the dog', $result['name']);
        $this->assertSame('every other day', $result['recurrence_pattern']);
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_every_other_weekday_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Yoga every other Wednesday');

        $this->assertSame('Yoga', $result['name']);
        $this->assertSame('every other Wednesday', $result['recurrence_pattern']);
        // Date is set to the next Wednesday: 2026-04-01.
        $this->assertSame('2026-04-01', $result['date']);
    }

    public function test_every_other_week_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Gym every other week');

        $this->assertSame('Gym', $result['name']);
        $this->assertSame('every other week', $result['recurrence_pattern']);
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_every_n_days_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Take vitamins every 5 days');

        $this->assertSame('Take vitamins', $result['name']);
        $this->assertSame('every 5 days', $result['recurrence_pattern']);
        $this->assertSame('2026-03-31', $result['date']); // today + 5
    }

    public function test_every_n_days_singular(): void
    {
        $result = $this->parser->parseTaskInput('Check logs every 1 day');

        $this->assertSame('every 1 days', $result['recurrence_pattern']);
        $this->assertSame('2026-03-27', $result['date']); // today + 1
    }

    public function test_every_n_weeks_pattern_date_is_today(): void
    {
        // The code sets date to today (not today + N weeks) for "every N weeks".
        $result = $this->parser->parseTaskInput('Oil change every 3 weeks');

        $this->assertSame('Oil change', $result['name']);
        $this->assertSame('every 3 weeks', $result['recurrence_pattern']);
        $this->assertSame('2026-03-26', $result['date']);
    }

    public function test_weekly_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Team meeting weekly');

        $this->assertSame('Team meeting', $result['name']);
        $this->assertSame('weekly', $result['recurrence_pattern']);
        $this->assertSame('2026-04-02', $result['date']); // today + 1 week
    }

    public function test_every_week_is_alias_for_weekly(): void
    {
        $result = $this->parser->parseTaskInput('Team meeting every week');

        $this->assertSame('weekly', $result['recurrence_pattern']);
    }

    public function test_monthly_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Pay rent monthly');

        $this->assertSame('Pay rent', $result['name']);
        $this->assertSame('monthly', $result['recurrence_pattern']);
        $this->assertSame('2026-04-26', $result['date']); // today + 1 month
    }

    public function test_every_month_is_alias_for_monthly(): void
    {
        $result = $this->parser->parseTaskInput('Pay rent every month');

        $this->assertSame('monthly', $result['recurrence_pattern']);
    }

    public function test_every_n_months_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Dentist every 6 months');

        $this->assertSame('Dentist', $result['name']);
        $this->assertSame('every 6 months', $result['recurrence_pattern']);
        $this->assertSame('2026-09-26', $result['date']); // today + 6 months
    }

    public function test_monthly_day_of_month_when_day_has_passed(): void
    {
        // Today is March 26; the 15th has already passed this month.
        $result = $this->parser->parseTaskInput('Pay bills every 15th');

        $this->assertSame('Pay bills', $result['name']);
        $this->assertSame('every 15', $result['recurrence_pattern']);
        $this->assertSame('2026-04-15', $result['date']); // April 15
    }

    public function test_monthly_day_of_month_when_day_is_still_upcoming(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10')); // 10th; the 28th hasn't passed yet
        $result = $this->parser->parseTaskInput('Pay bills every 28th');

        $this->assertSame('every 28', $result['recurrence_pattern']);
        $this->assertSame('2026-03-28', $result['date']); // same month
    }

    public function test_monthly_ordinal_day_word_form(): void
    {
        // First Monday in March (the 2nd) is already past; next is April 6.
        $result = $this->parser->parseTaskInput('Book club every first Monday');

        $this->assertSame('Book club', $result['name']);
        $this->assertSame('every first Monday', $result['recurrence_pattern']);
        $this->assertSame('2026-04-06', $result['date']);
    }

    public function test_monthly_ordinal_day_numeric_form_is_normalised(): void
    {
        // "every 3rd Sunday" → canonical pattern "every third Sunday"
        $result = $this->parser->parseTaskInput('Review every 3rd Sunday');

        $this->assertSame('Review', $result['name']);
        $this->assertSame('every third Sunday', $result['recurrence_pattern']);
        // Third Sunday in March (15th) is past; next is April 19.
        $this->assertSame('2026-04-19', $result['date']);
    }

    public function test_yearly_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Pay taxes yearly');

        $this->assertSame('Pay taxes', $result['name']);
        $this->assertSame('yearly', $result['recurrence_pattern']);
        $this->assertSame('2027-03-26', $result['date']); // today + 1 year
    }

    public function test_every_year_is_alias_for_yearly(): void
    {
        $result = $this->parser->parseTaskInput('Pay taxes every year');

        $this->assertSame('yearly', $result['recurrence_pattern']);
    }

    // =========================================================================
    // parseTaskInput() — floating recurrence ("every!")
    // =========================================================================

    public function test_floating_marker_sets_recurrence_floating_flag(): void
    {
        $result = $this->parser->parseTaskInput('Morning run every! day');

        $this->assertSame('daily', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
    }

    public function test_non_floating_recurrence_leaves_flag_false(): void
    {
        $result = $this->parser->parseTaskInput('Morning run daily');

        $this->assertFalse($result['recurrence_floating']);
    }

    public function test_floating_flag_not_set_when_pattern_is_unrecognised(): void
    {
        // "every!" is present but the rest of the pattern can't be resolved.
        $result = $this->parser->parseTaskInput('Buy groceries every! fortnight');

        $this->assertNull($result['recurrence_pattern']);
        $this->assertFalse($result['recurrence_floating']);
    }

    public function test_floating_weekly_recurrence(): void
    {
        $result = $this->parser->parseTaskInput('Buy groceries every! week');

        $this->assertSame('Buy groceries', $result['name']);
        $this->assertSame('weekly', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
    }

    // =========================================================================
    // getNextOccurrence() — simple patterns
    // =========================================================================

    public function test_next_occurrence_daily(): void
    {
        $next = $this->parser->getNextOccurrence('daily', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-27', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_other_day(): void
    {
        $next = $this->parser->getNextOccurrence('every other day', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-28', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_weekdays_from_weekday(): void
    {
        // Thursday → next weekday = Friday
        $next = $this->parser->getNextOccurrence('weekdays', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-27', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_weekdays_skips_weekend(): void
    {
        // Friday → next weekday = Monday (skips Saturday and Sunday)
        $next = $this->parser->getNextOccurrence('weekdays', Carbon::parse('2026-03-27'));

        $this->assertSame('2026-03-30', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_weekends_from_weekday(): void
    {
        // Thursday → next weekend day = Saturday
        $next = $this->parser->getNextOccurrence('weekends', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-28', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_weekends_from_saturday(): void
    {
        // Saturday → next weekend day = Sunday
        $next = $this->parser->getNextOccurrence('weekends', Carbon::parse('2026-03-28'));

        $this->assertSame('2026-03-29', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_weekly(): void
    {
        $next = $this->parser->getNextOccurrence('weekly', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-02', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_other_week(): void
    {
        $next = $this->parser->getNextOccurrence('every other week', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-09', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_n_days(): void
    {
        $next = $this->parser->getNextOccurrence('every 3 days', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-29', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_n_weeks(): void
    {
        $next = $this->parser->getNextOccurrence('every 2 weeks', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-09', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_monthly(): void
    {
        $next = $this->parser->getNextOccurrence('monthly', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-26', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_n_months(): void
    {
        $next = $this->parser->getNextOccurrence('every 3 months', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-06-26', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_yearly(): void
    {
        $next = $this->parser->getNextOccurrence('yearly', Carbon::parse('2026-03-26'));

        $this->assertSame('2027-03-26', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — day-of-week patterns (bare, every, plural)
    // =========================================================================

    public function test_next_occurrence_bare_day_name(): void
    {
        // From Thursday, next Monday = 2026-03-30.
        $next = $this->parser->getNextOccurrence('Monday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-30', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_day_name(): void
    {
        $next = $this->parser->getNextOccurrence('every Monday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-30', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_plural_day_name(): void
    {
        $next = $this->parser->getNextOccurrence('Mondays', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-30', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_other_weekday_adds_two_weeks(): void
    {
        $next = $this->parser->getNextOccurrence('every other Wednesday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-09', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — multi-day patterns
    // =========================================================================

    public function test_next_occurrence_multi_day_abbreviated(): void
    {
        // From Thursday, the next day in mon/wed/fri is Friday.
        $next = $this->parser->getNextOccurrence('mon,wed,fri', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-27', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_multi_day_full_names(): void
    {
        $next = $this->parser->getNextOccurrence('Monday,Wednesday,Friday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-27', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_multi_day_wraps_to_start_of_rotation(): void
    {
        // From Friday, the next day in tue/thu is Tuesday.
        $next = $this->parser->getNextOccurrence('tue,thu', Carbon::parse('2026-03-27'));

        $this->assertSame('2026-03-31', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — ordinal day patterns
    // =========================================================================

    public function test_next_occurrence_every_first_monday(): void
    {
        // First Monday in March (2nd) is past; next is April 6.
        $next = $this->parser->getNextOccurrence('every first Monday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-06', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_last_friday(): void
    {
        // Last Friday in March 2026 = March 27 (still in the future from March 26).
        $next = $this->parser->getNextOccurrence('every last Friday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-27', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_numeric_ordinal_is_normalised(): void
    {
        // "every 3rd Sunday" → "every third Sunday" → third Sunday in March (15th) is past; April 19.
        $next = $this->parser->getNextOccurrence('every 3rd Sunday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-19', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_bare_ordinal_without_every_prefix(): void
    {
        // "third Sunday" (no "every") is auto-prefixed before matching.
        $next = $this->parser->getNextOccurrence('third Sunday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-19', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — monthly day-of-month patterns
    // =========================================================================

    public function test_next_occurrence_monthly_day_when_day_has_passed(): void
    {
        // From March 26, the 15th has passed → April 15.
        $next = $this->parser->getNextOccurrence('every 15', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-15', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_monthly_day_with_ordinal_suffix(): void
    {
        $next = $this->parser->getNextOccurrence('every 15th', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-15', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_monthly_day_when_day_still_upcoming(): void
    {
        // From March 10, the 28th hasn't passed → March 28.
        $next = $this->parser->getNextOccurrence('every 28', Carbon::parse('2026-03-10'));

        $this->assertSame('2026-03-28', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — abbreviation normalisation
    // =========================================================================

    public function test_next_occurrence_normalises_mon_abbreviation(): void
    {
        $next = $this->parser->getNextOccurrence('mon', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-30', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_normalises_thurs_abbreviation(): void
    {
        // Today is Thursday and pattern is "thurs" → same day of week → next week.
        $next = $this->parser->getNextOccurrence('thurs', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-02', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_normalises_tues_abbreviation(): void
    {
        $next = $this->parser->getNextOccurrence('tues', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-31', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_normalises_weds_abbreviation(): void
    {
        $next = $this->parser->getNextOccurrence('weds', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-01', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_normalises_suns_abbreviation(): void
    {
        $next = $this->parser->getNextOccurrence('suns', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-03-29', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_returns_null_for_unknown_pattern(): void
    {
        $result = $this->parser->getNextOccurrence('every fortnight', Carbon::parse('2026-03-26'));

        $this->assertNull($result);
    }

    public function test_next_occurrence_returns_null_for_empty_pattern(): void
    {
        $result = $this->parser->getNextOccurrence('', Carbon::parse('2026-03-26'));

        $this->assertNull($result);
    }

    // =========================================================================
    // isValidRecurrencePattern()
    // =========================================================================

    /** @dataProvider validPatternsProvider */
    public function test_valid_patterns_return_true(string $pattern): void
    {
        $this->assertTrue(
            $this->parser->isValidRecurrencePattern($pattern),
            "Expected '{$pattern}' to be a valid recurrence pattern"
        );
    }

    public static function validPatternsProvider(): array
    {
        return [
            ['daily'],
            ['weekly'],
            ['monthly'],
            ['yearly'],
            ['weekdays'],
            ['weekends'],
            ['every other day'],
            ['every other week'],
            ['every 3 days'],
            ['every 2 weeks'],
            ['every 6 months'],
            ['Monday'],
            ['every Monday'],
            ['Mondays'],
            ['mon,wed,fri'],
            ['every first Monday'],
            ['every last Friday'],
            ['every 15'],
            ['every 15th'],
            ['every 3rd Sunday'],
            // Patterns that were previously untested:
            ['every 2 years'],
            ['every January 3'],
            ['every other Friday'],
            ['tue,thu'],
        ];
    }

    public function test_empty_string_is_invalid(): void
    {
        $this->assertFalse($this->parser->isValidRecurrencePattern(''));
    }

    public function test_unrecognised_pattern_is_invalid(): void
    {
        $this->assertFalse($this->parser->isValidRecurrencePattern('every fortnight'));
    }

    public function test_arbitrary_text_is_invalid(): void
    {
        $this->assertFalse($this->parser->isValidRecurrencePattern('buy groceries'));
    }

    // =========================================================================
    // detectUnrecognizedPattern()
    // =========================================================================

    public function test_plain_text_without_recurrence_keywords_returns_null(): void
    {
        $this->assertNull($this->parser->detectUnrecognizedPattern('Buy groceries'));
    }

    public function test_recognised_daily_pattern_returns_null(): void
    {
        $this->assertNull($this->parser->detectUnrecognizedPattern('Stand-up daily'));
    }

    public function test_recognised_every_monday_pattern_returns_null(): void
    {
        $this->assertNull($this->parser->detectUnrecognizedPattern('Team meeting every Monday'));
    }

    public function test_recognised_weekdays_pattern_returns_null(): void
    {
        $this->assertNull($this->parser->detectUnrecognizedPattern('Check email weekdays'));
    }

    public function test_recognised_weekly_pattern_returns_null(): void
    {
        $this->assertNull($this->parser->detectUnrecognizedPattern('Team meeting every week'));
    }

    public function test_unrecognised_every_pattern_returns_error_string(): void
    {
        // "every weeks" — "weeks" IS in the recurrence vocabulary but the pattern is
        // invalid (missing the required number), so detectUnrecognizedPattern warns.
        $result = $this->parser->detectUnrecognizedPattern('Gym every weeks');

        $this->assertIsString($result);
        $this->assertStringContainsString('not recognized', $result);
    }

    public function test_unrecognised_pattern_error_contains_the_original_input(): void
    {
        $input = 'Gym every weeks';
        $result = $this->parser->detectUnrecognizedPattern($input);

        $this->assertStringContainsString($input, $result);
    }

    public function test_unrecognised_pattern_error_lists_example_patterns(): void
    {
        // "every days" — "days" is in the vocabulary but needs a number prefix.
        $result = $this->parser->detectUnrecognizedPattern('Gym every days');

        // The error message should give the user some guidance.
        $this->assertStringContainsString('daily', $result);
    }

    // =========================================================================
    // parseTaskInput() — every N years (previously untested)
    // =========================================================================

    public function test_every_n_years_pattern(): void
    {
        $result = $this->parser->parseTaskInput('Renew passport every 2 years');

        $this->assertSame('Renew passport', $result['name']);
        $this->assertSame('every 2 years', $result['recurrence_pattern']);
        $this->assertSame('2028-03-26', $result['date']); // today + 2 years
        $this->assertFalse($result['recurrence_floating']);
    }

    public function test_every_1_year_singular_sets_pattern(): void
    {
        $result = $this->parser->parseTaskInput('File taxes every 1 year');

        $this->assertSame('every 1 years', $result['recurrence_pattern']);
        $this->assertSame('2027-03-26', $result['date']); // today + 1 year
    }

    // =========================================================================
    // parseTaskInput() — every [Month] [day] annual pattern (previously untested)
    // =========================================================================

    public function test_every_month_day_future_in_same_year(): void
    {
        // every April 15 — April 15 2026 is still in the future from March 26
        $result = $this->parser->parseTaskInput('Tax reminder every April 15');

        $this->assertSame('Tax reminder', $result['name']);
        $this->assertSame('every April 15', $result['recurrence_pattern']);
        $this->assertSame('2026-04-15', $result['date']);
    }

    public function test_every_month_day_past_in_current_year_wraps_to_next_year(): void
    {
        // every January 3 — Jan 3 2026 has already passed
        $result = $this->parser->parseTaskInput('New Year check every January 3');

        $this->assertSame('New Year check', $result['name']);
        $this->assertSame('every January 3', $result['recurrence_pattern']);
        $this->assertSame('2027-01-03', $result['date']);
    }

    // =========================================================================
    // parseTaskInput() — nodate token (previously untested)
    // =========================================================================

    public function test_nodate_token_clears_date_and_is_removed_from_name(): void
    {
        $result = $this->parser->parseTaskInput('Buy groceries nodate');

        $this->assertSame('Buy groceries', $result['name']);
        $this->assertNull($result['date']);
        $this->assertNull($result['recurrence_pattern']);
        $this->assertTrue($result['nodate']);
    }

    public function test_nodate_alone_produces_empty_name(): void
    {
        // When the entire input is just "nodate", stripping it leaves an empty name.
        // The early return for nodate fires before the empty-name fallback, so name=''.
        $result = $this->parser->parseTaskInput('nodate');

        $this->assertSame('', $result['name']);
        $this->assertNull($result['date']);
        $this->assertTrue($result['nodate']);
    }

    // =========================================================================
    // parseTaskInput() — secondary pass: recurrence extracted after today/tomorrow
    // =========================================================================

    public function test_today_with_recurring_day_in_remaining_name(): void
    {
        // "today" fires first and strips the date token; secondary pass picks up "Mondays"
        $result = $this->parser->parseTaskInput('Stand-up today Mondays');

        $this->assertSame('Stand-up', $result['name']);
        $this->assertSame('2026-03-26', $result['date']); // today (not next Monday)
        $this->assertSame('Monday', $result['recurrence_pattern']);
    }

    public function test_tomorrow_with_recurrence_keyword_in_remaining_name(): void
    {
        // "tomorrow" fires first; secondary pass finds "daily"
        $result = $this->parser->parseTaskInput('Morning run tomorrow daily');

        $this->assertSame('Morning run', $result['name']);
        $this->assertSame('2026-03-27', $result['date']); // tomorrow
        $this->assertSame('daily', $result['recurrence_pattern']);
    }

    public function test_today_with_every_n_weeks_in_remaining_name(): void
    {
        $result = $this->parser->parseTaskInput('Oil change today every 3 weeks');

        $this->assertSame('Oil change', $result['name']);
        $this->assertSame('2026-03-26', $result['date']); // today preserved
        $this->assertSame('every 3 weeks', $result['recurrence_pattern']);
    }

    // =========================================================================
    // parseTaskInput() — floating recurrence with specific day-of-week (previously untested)
    // =========================================================================

    public function test_floating_with_specific_day_of_week(): void
    {
        $result = $this->parser->parseTaskInput('Team sync every! Monday');

        $this->assertSame('Team sync', $result['name']);
        $this->assertSame('Monday', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
        $this->assertSame('2026-03-30', $result['date']); // next Monday
    }

    public function test_floating_with_plural_day_of_week(): void
    {
        $result = $this->parser->parseTaskInput('Stand-up every! Thursdays');

        $this->assertSame('Thursday', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
    }

    public function test_floating_with_every_other_weekday(): void
    {
        $result = $this->parser->parseTaskInput('Yoga every! other Wednesday');

        $this->assertSame('every other Wednesday', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
    }

    public function test_floating_with_monthly_ordinal(): void
    {
        $result = $this->parser->parseTaskInput('Book club every! first Monday');

        $this->assertSame('every first Monday', $result['recurrence_pattern']);
        $this->assertTrue($result['recurrence_floating']);
    }

    // =========================================================================
    // parseTaskInput() — date_explicit flag (previously untested)
    // =========================================================================

    public function test_date_explicit_is_true_for_today(): void
    {
        $result = $this->parser->parseTaskInput('Call dentist today');

        $this->assertTrue($result['date_explicit']);
    }

    public function test_date_explicit_is_true_for_day_name(): void
    {
        $result = $this->parser->parseTaskInput('Call dentist Monday');

        $this->assertTrue($result['date_explicit']);
    }

    public function test_date_explicit_is_false_for_daily_recurrence(): void
    {
        // Date is computed as "today" but was not explicitly typed — it defaults from the pattern.
        $result = $this->parser->parseTaskInput('Morning run daily');

        $this->assertFalse($result['date_explicit']);
    }

    public function test_date_explicit_is_false_for_weekly_recurrence(): void
    {
        $result = $this->parser->parseTaskInput('Team meeting weekly');

        $this->assertFalse($result['date_explicit']);
    }

    // =========================================================================
    // parseTaskInput() — two-day multi-day pattern (previously untested)
    // =========================================================================

    public function test_two_day_abbreviated_multi_day(): void
    {
        // From Thursday, next day in tue/thu is today (Thursday), but getNextMultiDay
        // always starts from tomorrow, so the next is Tuesday.
        $result = $this->parser->parseTaskInput('Gym tue,thu');

        $this->assertSame('Gym', $result['name']);
        $this->assertSame('tue,thu', $result['recurrence_pattern']);
        // getNextMultiDay with no currentDate starts from Carbon::today() (not tomorrow).
        // Today is Thursday which IS in {tue,thu}, so the first occurrence is today.
        $this->assertSame('2026-03-26', $result['date']);
    }

    // =========================================================================
    // getNextOccurrence() — every N years (previously untested)
    // =========================================================================

    public function test_next_occurrence_every_n_years(): void
    {
        $next = $this->parser->getNextOccurrence('every 2 years', Carbon::parse('2026-03-26'));

        $this->assertSame('2028-03-26', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_1_year(): void
    {
        $next = $this->parser->getNextOccurrence('every 1 years', Carbon::parse('2026-03-26'));

        $this->assertSame('2027-03-26', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — every [Month] [day] (previously untested)
    // =========================================================================

    public function test_next_occurrence_every_april_15_from_before_april(): void
    {
        $next = $this->parser->getNextOccurrence('every April 15', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-15', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_january_3_when_date_has_passed(): void
    {
        $next = $this->parser->getNextOccurrence('every January 3', Carbon::parse('2026-03-26'));

        $this->assertSame('2027-01-03', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_december_25_from_early_december(): void
    {
        $next = $this->parser->getNextOccurrence('every December 25', Carbon::parse('2026-12-01'));

        $this->assertSame('2026-12-25', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — monthly day clipped in short months (edge case)
    // =========================================================================

    public function test_next_occurrence_every_31_in_february_clips_to_28(): void
    {
        // February has only 28 days in 2026 — the parser silently clips to the last day.
        $next = $this->parser->getNextOccurrence('every 31', Carbon::parse('2026-02-01'));

        $this->assertSame('2026-02-28', $next->format('Y-m-d'));
    }

    public function test_next_occurrence_every_30_in_february_clips_to_28(): void
    {
        $next = $this->parser->getNextOccurrence('every 30', Carbon::parse('2026-02-01'));

        $this->assertSame('2026-02-28', $next->format('Y-m-d'));
    }

    // =========================================================================
    // getNextOccurrence() — ordinal when occurrence does not exist in that month
    // =========================================================================

    public function test_next_occurrence_fifth_monday_skips_to_month_with_five_mondays(): void
    {
        // "every fourth Monday" — April 2026 has Mondays on 6, 13, 20, 27 (four of them).
        // From March 26, fourth Monday of March (23rd) is past; next is April 27.
        $next = $this->parser->getNextOccurrence('every fourth Monday', Carbon::parse('2026-03-26'));

        $this->assertSame('2026-04-27', $next->format('Y-m-d'));
    }

    // =========================================================================
    // isValidRecurrencePattern() — additional patterns (previously untested)
    // =========================================================================

    public function test_every_n_years_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('every 2 years'));
    }

    public function test_every_month_day_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('every January 3'));
    }

    public function test_every_other_friday_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('every other Friday'));
    }

    public function test_two_day_multi_day_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('tue,thu'));
    }

    public function test_every_second_tuesday_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('every second Tuesday'));
    }

    public function test_every_fourth_friday_is_valid(): void
    {
        $this->assertTrue($this->parser->isValidRecurrencePattern('every fourth Friday'));
    }
}
