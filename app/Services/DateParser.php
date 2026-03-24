<?php

namespace App\Services;

use Carbon\Carbon;

class DateParser
{
    public function parseTaskInput(string $input): array
    {
        // Detect Todoist-style floating recurrence marker "!" (e.g., "every! month")
        // "every!" means floating (next date relative to completion), "every" means fixed
        $recurrenceFloating = (bool) preg_match('/\bevery!\s*/i', $input);

        // Normalize "every!" to "every " for pattern matching
        $input = preg_replace('/\bevery!\s*/i', 'every ', $input);
        $input = trim($input);

        $result = [
            'name' => $input,
            'date' => null,
            'time' => null,
            'recurrence_pattern' => null,
            'recurrence_floating' => false,
        ];

        $patterns = [
            'daily' => '/\b(daily|every day)\b/i',
            'weekdays' => '/\bweekdays\b/i',
            'weekends' => '/\bweekends\b/i',
            'every_other_day_literal' => '/\bevery other day\b/i',
            'every_other_weekday' => '/\bevery other (monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i',
            'every_other_week' => '/\bevery other week\b/i',
            'every_n_days' => '/\bevery (\d+) days?\b/i',
            'every_n_months' => '/\bevery (\d+) months?\b/i',
            'monthly' => '/\b(monthly|every month)\b/i',
            'weekly_literal' => '/\b(weekly|every week)\b/i',
            'every_n_weeks' => '/\bevery (\d+) weeks?\b/i',
            'tomorrow' => '/\btomorrow\b/i',
            'today' => '/\btoday\b/i',
            'next_day_of_week' => '/\bnext\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i',
            'day_of_week' => '/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)s?\b/i',
            'multi_days_full' => '/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)(\s*,\s*(monday|tuesday|wednesday|thursday|friday|saturday|sunday))+\b/i',
            'multi_days' => '/\b(mon|tues?|weds?|thurs?|fri|sat|suns?)(\s*,\s*(mon|tues?|weds?|thurs?|fri|sat|suns?))+\b/i',
            'monthly_ordinal' => '/\bevery (first|1st|second|2nd|third|3rd|fourth|4th|last) (monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i',
            'monthly_day' => '/\bevery (\d{1,2})(st|nd|rd|th)?(?!\s+(days?|weeks?|months?|years?))\b/i',
            'yearly' => '/\b(yearly|every year)\b/i',
            'date_month_day' => '/\b(january|february|march|april|may|june|july|august|september|october|november|december) (\d{1,2})\b/i',
            'date_slash' => '/\b(\d{1,2})\/(\d{1,2})\b/',
            'date_iso' => '/\b(\d{4})-(\d{2})-(\d{2})\b/',
        ];

        if (preg_match($patterns['daily'], $input, $matches)) {
            $result['recurrence_pattern'] = 'daily';
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['daily'], '', $input));
        } elseif (preg_match($patterns['weekdays'], $input, $matches)) {
            $result['recurrence_pattern'] = 'weekdays';
            $result['date'] = $this->getNextWeekday()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['weekdays'], '', $input));
        } elseif (preg_match($patterns['weekends'], $input, $matches)) {
            $result['recurrence_pattern'] = 'weekends';
            $result['date'] = $this->getNextWeekend()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['weekends'], '', $input));
        } elseif (preg_match($patterns['every_other_day_literal'], $input, $matches)) {
            $result['recurrence_pattern'] = 'every other day';
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_other_day_literal'], '', $input));
        } elseif (preg_match($patterns['every_other_weekday'], $input, $matches)) {
            $dayName = ucfirst(strtolower($matches[1]));
            $result['recurrence_pattern'] = "every other {$dayName}";
            $result['date'] = $this->getNextDayOfWeek($dayName)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_other_weekday'], '', $input));
        } elseif (preg_match($patterns['every_other_week'], $input, $matches)) {
            $result['recurrence_pattern'] = 'every other week';
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_other_week'], '', $input));
        } elseif (preg_match($patterns['every_n_days'], $input, $matches)) {
            $days = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$days} days";
            $result['date'] = Carbon::today()->addDays($days)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_n_days'], '', $input));
        } elseif (preg_match($patterns['every_n_months'], $input, $matches)) {
            $months = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$months} months";
            $result['date'] = Carbon::today()->addMonths($months)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_n_months'], '', $input));
        } elseif (preg_match($patterns['monthly'], $input, $matches)) {
            $result['recurrence_pattern'] = 'monthly';
            $result['date'] = Carbon::today()->addMonth()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['monthly'], '', $input));
        } elseif (preg_match($patterns['weekly_literal'], $input, $matches)) {
            $result['recurrence_pattern'] = 'weekly';
            $result['date'] = Carbon::today()->addWeek()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['weekly_literal'], '', $input));
        } elseif (preg_match($patterns['every_n_weeks'], $input, $matches)) {
            $weeks = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$weeks} weeks";
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_n_weeks'], '', $input));
        } elseif (preg_match($patterns['tomorrow'], $input)) {
            $result['date'] = Carbon::tomorrow()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['tomorrow'], '', $input));
        } elseif (preg_match($patterns['today'], $input)) {
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['today'], '', $input));
        } elseif (preg_match($patterns['next_day_of_week'], $input, $matches)) {
            $dayName = ucfirst(strtolower($matches[1]));
            $result['date'] = $this->getNextDayOfWeek($dayName)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['next_day_of_week'], '', $input));
        } elseif (preg_match($patterns['multi_days_full'], $input, $matches)) {
            $abbr = $this->normalizeMultiDayToAbbr($matches[0]);
            $result['recurrence_pattern'] = $abbr;
            $result['date'] = $this->getNextMultiDay($abbr)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['multi_days_full'], '', $input));
        } elseif (preg_match_all($patterns['day_of_week'], $input, $allDayMatches)) {
            // Scan ALL day-name hits to handle two tricky cases:
            // 1. "Friday Fridays" — preg_match alone finds "Friday" (non-recurring),
            //    missing the plural "Fridays" that signals recurrence.
            // 2. "letter on Sunday Tuesday" — "Sunday" is embedded in the sentence;
            //    the scheduling day is the LAST day name in the input.
            // Strategy: keep updating $dayName on every non-recurring hit (so we end
            // up with the last one), but break immediately if we find a recurring hit.
            $dayName     = null;
            $isRecurring = false;
            foreach ($allDayMatches[1] as $idx => $captured) {
                $full   = $allDayMatches[0][$idx];
                $plural = strlen($full) > strlen($captured);
                $every  = (bool) preg_match('/\bevery\s+' . preg_quote($captured, '/') . '\b/i', $input);
                if ($plural || $every) {
                    $dayName     = ucfirst(strtolower($captured));
                    $isRecurring = true;
                    break; // first recurring indicator wins
                }
                // Always update so the loop leaves us with the LAST non-recurring day
                $dayName = ucfirst(strtolower($captured));
            }
            if ($isRecurring) {
                $result['recurrence_pattern'] = $dayName;
            }
            $result['date'] = $this->getNextDayOfWeek($dayName)->format('Y-m-d');
            // Remove only the selected day's tokens from the title so that other day
            // names embedded in the prose ("Letter on Sunday Tuesday" → "Letter on Sunday")
            // are left intact.  Collapse any resulting double-spaces.
            $cap = preg_quote(strtolower($dayName), '/');
            $result['name'] = trim(preg_replace('/\s+/', ' ',
                preg_replace('/\bevery\s+' . $cap . 's?\b\s*|\b' . $cap . 's?\b\s*/i', '', $input)
            ));
        } elseif (preg_match($patterns['multi_days'], $input, $matches)) {
            $result['recurrence_pattern'] = $matches[0];
            $result['date'] = $this->getNextMultiDay($matches[0])->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['multi_days'], '', $input));
        } elseif (preg_match($patterns['monthly_ordinal'], $input, $matches)) {
            $numericOrdinals = ['1st' => 'first', '2nd' => 'second', '3rd' => 'third', '4th' => 'fourth'];
            $ordinal = $numericOrdinals[strtolower($matches[1])] ?? strtolower($matches[1]);
            $dayName = $matches[2];
            $result['recurrence_pattern'] = "every {$ordinal} {$dayName}";
            $result['date'] = $this->getNextOrdinalDay($ordinal, $dayName)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['monthly_ordinal'], '', $input));
        } elseif (preg_match($patterns['monthly_day'], $input, $matches)) {
            $day = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$day}";
            $result['date'] = $this->getNextMonthDay($day)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['monthly_day'], '', $input));
        } elseif (preg_match($patterns['yearly'], $input, $matches)) {
            $result['recurrence_pattern'] = 'yearly';
            $result['date'] = Carbon::today()->addYear()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['yearly'], '', $input));
        } elseif (preg_match($patterns['date_month_day'], $input, $matches)) {
            $month = $matches[1];
            $day = (int) $matches[2];
            $result['date'] = $this->getNextMonthDayDate($month, $day)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['date_month_day'], '', $input));
        } elseif (preg_match($patterns['date_slash'], $input, $matches)) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $result['date'] = $this->getNextDate($month, $day)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['date_slash'], '', $input));
        } elseif (preg_match($patterns['date_iso'], $input, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $result['date'] = Carbon::create($year, $month, $day)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['date_iso'], '', $input));
        }

        $result['name'] = trim($result['name']);
        if (empty($result['name'])) {
            $result['name'] = $input;
        }

        // Set floating flag if "every!" was used and a recurrence pattern was found
        if ($recurrenceFloating && $result['recurrence_pattern']) {
            $result['recurrence_floating'] = true;
        }

        return $result;
    }

    protected function getNextWeekday(): Carbon
    {
        $date = Carbon::today();
        if ($date->isWeekend()) {
            return $date->next(Carbon::MONDAY);
        }
        return $date;
    }

    protected function getNextWeekend(): Carbon
    {
        $date = Carbon::today();
        if ($date->isSaturday() || $date->isSunday()) {
            return $date;
        }
        return $date->next(Carbon::SATURDAY);
    }

    protected function getNextDayOfWeek(string $dayName, Carbon $currentDate = null): Carbon
    {
        $date = $currentDate ? $currentDate->copy() : Carbon::today();
        $targetDay = constant('Carbon\Carbon::' . strtoupper($dayName));

        if ($date->dayOfWeek === $targetDay) {
            return $date->addWeek();
        }

        return $date->next($targetDay);
    }

    protected function normalizeMultiDayToAbbr(string $days): string
    {
        $fullToAbbr = [
            'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
            'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun',
        ];
        $parts = preg_split('/\s*,\s*/', strtolower(trim($days)));
        $abbrs = array_map(fn($d) => $fullToAbbr[trim($d)] ?? trim($d), $parts);
        return implode(',', $abbrs);
    }

    protected function getNextMultiDay(string $days, Carbon $currentDate = null): Carbon
    {
        $dayMap = [
            'sun' => Carbon::SUNDAY,  'suns' => Carbon::SUNDAY,
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY, 'tues' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY, 'weds' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY, 'thurs' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
        ];

        $dayParts = explode(',', strtolower($days));
        $targetDays = array_map(function ($day) use ($dayMap) {
            return $dayMap[trim($day)] ?? null;
        }, $dayParts);
        $targetDays = array_values(array_filter($targetDays, fn($v) => $v !== null));

        $date = $currentDate ? $currentDate->copy()->addDay() : Carbon::today();
        $found = false;

        for ($i = 0; $i < 7; $i++) {
            if (in_array($date->dayOfWeek, $targetDays)) {
                $found = true;
                break;
            }
            $date->addDay();
        }

        return $date;
    }

    protected function getNextOrdinalDay(string $ordinal, string $dayName, Carbon $currentDate = null): Carbon
    {
        $referenceDate = $currentDate ? $currentDate->copy() : Carbon::today();
        $date = $referenceDate->copy()->startOfMonth();
        $targetDay = constant('Carbon\Carbon::' . strtoupper($dayName));

        $occurrences = [];
        $currentMonth = $date->month;
        while ($date->month === $currentMonth) {
            if ($date->dayOfWeek === $targetDay) {
                $occurrences[] = $date->copy();
            }
            $date->addDay();
        }

        $ordinalMap = [
            'first' => 0,
            'second' => 1,
            'third' => 2,
            'fourth' => 3,
            'last' => count($occurrences) - 1,
        ];

        $index = $ordinalMap[strtolower($ordinal)] ?? 0;
        $targetDate = $occurrences[$index] ?? $referenceDate;

        // If target date is on or before current date, get next month's occurrence
        if ($targetDate <= $referenceDate) {
            return $this->getNextOrdinalDay($ordinal, $dayName, $referenceDate->copy()->addMonth()->startOfMonth());
        }

        return $targetDate;
    }

    protected function getNextMonthDay(int $day, Carbon $currentDate = null): Carbon
    {
        $date = $currentDate ? $currentDate->copy() : Carbon::today();

        if ($date->day >= $day) {
            $date->addMonth();
        }

        $date->day = min($day, $date->daysInMonth);

        return $date;
    }

    protected function getNextMonthDayDate(string $month, int $day): Carbon
    {
        $monthNum = Carbon::parse($month . ' 1')->month;
        $year = Carbon::today()->year;

        $date = Carbon::create($year, $monthNum, $day);

        if ($date->isPast()) {
            $date->addYear();
        }

        return $date;
    }

    protected function getNextDate(int $month, int $day): Carbon
    {
        $year = Carbon::today()->year;
        $date = Carbon::create($year, $month, $day);

        if ($date->isPast()) {
            $date->addYear();
        }

        return $date;
    }

    public function getNextOccurrence(string $recurrencePattern, Carbon $currentDate): ?Carbon
    {
        if (!$recurrencePattern) {
            return null;
        }

        // Normalize pattern to lowercase and strip spaces around commas so that
        // "Mon, Tue, Wed" is treated the same as "Mon,Tue,Wed".
        $normalizedPattern = preg_replace('/\s*,\s*/', ',', strtolower($recurrencePattern));
        // Expand day name abbreviations to full names using whole-word boundaries.
        // We cannot use strtr() here because it does substring replacement — "thursday"
        // would become "thuday" if 'thurs' => 'thu' were applied as a substring.
        foreach ([
            'thurs' => 'thursday', 'tues'  => 'tuesday',
            'weds'  => 'wednesday', 'suns'  => 'sunday',
            'mon'   => 'monday',   'tue'   => 'tuesday',
            'wed'   => 'wednesday', 'thu'  => 'thursday',
            'fri'   => 'friday',   'sat'   => 'saturday',
            'sun'   => 'sunday',
        ] as $abbrev => $full) {
            $normalizedPattern = preg_replace('/\b' . $abbrev . '\b/', $full, $normalizedPattern);
        }

        if ($normalizedPattern === 'daily') {
            return $currentDate->copy()->addDay();
        }

        if ($normalizedPattern === 'every other day') {
            return $currentDate->copy()->addDays(2);
        }

        if ($normalizedPattern === 'weekdays') {
            $next = $currentDate->copy()->addDay();
            while ($next->isWeekend()) {
                $next->addDay();
            }
            return $next;
        }

        if ($normalizedPattern === 'weekends') {
            $next = $currentDate->copy()->addDay();
            while (!$next->isWeekend()) {
                $next->addDay();
            }
            return $next;
        }

        if ($normalizedPattern === 'weekly') {
            return $currentDate->copy()->addWeek();
        }

        if ($normalizedPattern === 'every other week') {
            return $currentDate->copy()->addWeeks(2);
        }

        if (preg_match('/^every (\d+) days?$/', $normalizedPattern, $matches)) {
            $days = (int) $matches[1];
            return $currentDate->copy()->addDays($days);
        }

        if (preg_match('/^every (\d+) weeks?$/', $normalizedPattern, $matches)) {
            $weeks = (int) $matches[1];
            return $currentDate->copy()->addWeeks($weeks);
        }

        if (preg_match('/^every other (monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $normalizedPattern, $matches)) {
            // "every other Wednesday" means 2 weeks from current date
            return $currentDate->copy()->addWeeks(2);
        }

        if (preg_match('/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $normalizedPattern)) {
            return $this->getNextDayOfWeek($normalizedPattern, $currentDate);
        }

        // Handle "every Wednesday" and plural "Wednesdays" entered directly in the field
        if (preg_match('/^every (monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $normalizedPattern, $matches)) {
            return $this->getNextDayOfWeek($matches[1], $currentDate);
        }

        if (preg_match('/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)s$/i', $normalizedPattern, $matches)) {
            return $this->getNextDayOfWeek($matches[1], $currentDate);
        }

        // Normalize full day names ("Monday,Wednesday") to abbreviations before multi-day check
        if (preg_match('/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)(\s*,\s*(monday|tuesday|wednesday|thursday|friday|saturday|sunday))+$/i', $normalizedPattern)) {
            $normalizedPattern = $this->normalizeMultiDayToAbbr($normalizedPattern);
        }

        if (preg_match('/^(mon|tue|wed|thu|fri|sat|sun)(,(mon|tue|wed|thu|fri|sat|sun))+$/i', $normalizedPattern)) {
            return $this->getNextMultiDay($normalizedPattern, $currentDate);
        }

        // Normalize ordinal day-of-month variants to canonical "every {word-ordinal} {day}" form.
        // Handles: "3rd Sunday", "every 3rd Sunday of the month", "third sunday of the month", etc.
        $normalizedPattern = preg_replace('/\s+of\s+(the|every)\s+month\s*$/i', '', $normalizedPattern);
        $normalizedPattern = preg_replace_callback(
            '/\b(1st|2nd|3rd|4th)\b(?=\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday))/i',
            fn($m) => ['1st' => 'first', '2nd' => 'second', '3rd' => 'third', '4th' => 'fourth'][strtolower($m[1])],
            $normalizedPattern
        );
        if (preg_match('/^(first|second|third|fourth|last)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $normalizedPattern)) {
            $normalizedPattern = 'every ' . $normalizedPattern;
        }

        if (preg_match('/^every (first|second|third|fourth|last) (monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $normalizedPattern, $matches)) {
            return $this->getNextOrdinalDay($matches[1], $matches[2], $currentDate);
        }

        if (preg_match('/^every (\d{1,2})(st|nd|rd|th)?$/', $normalizedPattern, $matches)) {
            $day = (int) $matches[1];
            return $this->getNextMonthDay($day, $currentDate);
        }

        if ($normalizedPattern === 'monthly') {
            return $currentDate->copy()->addMonth();
        }

        if (preg_match('/^every (\d+) months?$/', $normalizedPattern, $matches)) {
            $months = (int) $matches[1];
            return $currentDate->copy()->addMonths($months);
        }

        if ($normalizedPattern === 'yearly') {
            return $currentDate->copy()->addYear();
        }

        return null;
    }

    /**
     * Check if a recurrence pattern is valid/recognized.
     * Returns true if the pattern can be processed, false otherwise.
     */
    public function isValidRecurrencePattern(string $pattern): bool
    {
        if (empty($pattern)) {
            return false;
        }

        // Try to get next occurrence - if it returns a date, the pattern is valid
        $testDate = Carbon::today();
        $result = $this->getNextOccurrence($pattern, $testDate);

        return $result !== null;
    }

    /**
     * Check if the input contains recurrence keywords that we don't recognize.
     * Returns an error message if unrecognized pattern detected, null otherwise.
     */
    public function detectUnrecognizedPattern(string $input): ?string
    {
        // List of recurrence-related keywords that suggest the user is trying to specify a pattern
        $recurrenceKeywords = [
            'every',
            'daily',
            'weekly',
            'monthly',
            'yearly',
            'weekdays',
            'weekends',
        ];

        // Check if input contains any recurrence keywords
        $containsKeyword = false;
        foreach ($recurrenceKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $input)) {
                $containsKeyword = true;
                break;
            }
        }

        if (!$containsKeyword) {
            return null;
        }

        // Now check if we actually parsed a pattern from it
        $parsed = $this->parseTaskInput($input);
        if ($parsed['recurrence_pattern'] !== null) {
            // We recognized the pattern, all good
            return null;
        }

        // We found recurrence keywords but couldn't parse a pattern
        return "The recurrence pattern in '{$input}' was not recognized. Supported patterns include: daily, every other day, weekdays, weekends, weekly, every other week, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, monthly, every month, every 3 months, every 1st (monthly), every first Monday (monthly), yearly.";
    }
}
