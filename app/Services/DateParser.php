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

        // "nodate" token explicitly clears any date on the task.
        $nodate = (bool) preg_match('/\bnodate\b/i', $input);
        if ($nodate) {
            $input = trim(preg_replace('/\bnodate\b\s*/i', '', $input));
        }

        $result = [
            'name' => $input,
            'date' => null,
            'time' => null,
            'recurrence_pattern' => null,
            'recurrence_floating' => false,
            'nodate' => $nodate,
            // true when the date was explicitly typed by the user (today, tomorrow, a day
            // name, a specific month/day, etc.); false when it is a default computed from
            // the recurrence interval (yearly → today+1yr, weekly → today+1wk, etc.).
            // Controllers use this to decide whether to override a pre-filled date.
            'date_explicit' => false,
        ];

        if ($nodate) {
            return $result;
        }

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
            'multi_days' => '/\b(mon|tues?|weds?|thu(?:rs?)?|fri|sat|suns?)(\s*,\s*(mon|tues?|weds?|thu(?:rs?)?|fri|sat|suns?))+\b/i',
            'monthly_ordinal' => '/\bevery (first|1st|second|2nd|third|3rd|fourth|4th|last) (monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i',
            'monthly_day' => '/\bevery (\d{1,2})(st|nd|rd|th)?(?!\s+(days?|weeks?|months?|years?))\b/i',
            'yearly' => '/\b(yearly|every year)\b/i',
            'every_n_years' => '/\bevery (\d+) years?\b/i',
            'every_month_day' => '/\bevery (january|february|march|april|may|june|july|august|september|october|november|december) (\d{1,2})\b/i',
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
            $result['date_explicit'] = true;
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
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['tomorrow'], '', $input));
            // Secondary pass: extract any recurrence pattern left in the name after stripping "tomorrow"
            $result = $this->extractRecurrenceFromName($result, $patterns);
        } elseif (preg_match($patterns['today'], $input)) {
            $result['date'] = Carbon::today()->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['today'], '', $input));
            // Secondary pass: extract any recurrence pattern left in the name after stripping "today"
            $result = $this->extractRecurrenceFromName($result, $patterns);
        } elseif (preg_match($patterns['next_day_of_week'], $input, $matches)) {
            $dayName = ucfirst(strtolower($matches[1]));
            $result['date'] = $this->getNextDayOfWeek($dayName)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['next_day_of_week'], '', $input));
        } elseif (preg_match($patterns['monthly_ordinal'], $input, $matches)) {
            // Checked before day_of_week so that "every first Monday" is not swallowed
            // by the generic day-name branch (which can't see the ordinal qualifier).
            $numericOrdinals = ['1st' => 'first', '2nd' => 'second', '3rd' => 'third', '4th' => 'fourth'];
            $ordinal = $numericOrdinals[strtolower($matches[1])] ?? strtolower($matches[1]);
            $dayName = $matches[2];
            $result['recurrence_pattern'] = "every {$ordinal} {$dayName}";
            $result['date'] = $this->getNextOrdinalDay($ordinal, $dayName)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['monthly_ordinal'], '', $input));
        } elseif (preg_match($patterns['multi_days_full'], $input, $matches)) {
            $abbr = $this->normalizeMultiDayToAbbr($matches[0]);
            $result['recurrence_pattern'] = $abbr;
            $result['date'] = $this->getNextMultiDay($abbr)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['multi_days_full'], '', $input));
        } elseif (preg_match_all($patterns['day_of_week'], $input, $allDayMatches)) {
            // Scan ALL day-name hits to separate:
            // - A recurring day ("every Thursday", "Thursdays") → sets recurrence_pattern
            // - A plain day name → sets the first-occurrence date
            //
            // Cases handled:
            // 1. "Friday Fridays" — plural signals recurrence; singular is the date.
            // 2. "letter on Sunday Tuesday" — both plain; last one is the scheduling date,
            //    earlier ones stay in the title.
            // 3. "Do stuff Wednesday every Thursday" — Wednesday is the first-occurrence
            //    date, every Thursday is the recurrence pattern.
            // 4. Only a recurring day ("every Thursday") — date = next Thursday.
            $recurringDay    = null;
            $lastPlainDay    = null;
            foreach ($allDayMatches[1] as $idx => $captured) {
                $full   = $allDayMatches[0][$idx];
                $plural = strlen($full) > strlen($captured);
                $every  = (bool) preg_match('/\bevery\s+' . preg_quote($captured, '/') . '\b/i', $input);
                if (($plural || $every) && $recurringDay === null) {
                    $recurringDay = ucfirst(strtolower($captured));
                } elseif (!$plural && !$every) {
                    $lastPlainDay = ucfirst(strtolower($captured));
                }
            }

            if ($recurringDay !== null) {
                $result['recurrence_pattern'] = $recurringDay;
                // If a separate plain day was also present, use it as the first-occurrence date.
                $dateDay = $lastPlainDay ?? $recurringDay;
                $result['date'] = $this->getNextDayOfWeek($dateDay)->format('Y-m-d');
                $result['date_explicit'] = true;
                // Strip the recurring token ("every Thursday" / "Thursdays") …
                $rcap = preg_quote(strtolower($recurringDay), '/');
                $name = preg_replace('/\bevery\s+' . $rcap . 's?\b\s*|\b' . $rcap . 's?\b\s*/i', '', $input);
                // … and also strip the plain date day if it was a different day from the recurrence.
                if ($lastPlainDay !== null && strtolower($lastPlainDay) !== strtolower($recurringDay)) {
                    $dcap = preg_quote(strtolower($lastPlainDay), '/');
                    $name = preg_replace('/\b' . $dcap . 's?\b\s*/i', '', $name);
                }
                $result['name'] = trim(preg_replace('/\s+/', ' ', $name));
            } else {
                // Only plain day names — last one is the scheduling date; earlier ones
                // embedded in prose ("Letter on Sunday Tuesday" → "Letter on Sunday") stay.
                $dayName = $lastPlainDay;
                $result['date'] = $this->getNextDayOfWeek($dayName)->format('Y-m-d');
                $result['date_explicit'] = true;
                $cap = preg_quote(strtolower($dayName), '/');
                $result['name'] = trim(preg_replace('/\s+/', ' ',
                    preg_replace('/\bevery\s+' . $cap . 's?\b\s*|\b' . $cap . 's?\b\s*/i', '', $input)
                ));
            }
        } elseif (preg_match($patterns['multi_days'], $input, $matches)) {
            $result['recurrence_pattern'] = $matches[0];
            $result['date'] = $this->getNextMultiDay($matches[0])->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['multi_days'], '', $input));
        } elseif (preg_match($patterns['monthly_day'], $input, $matches)) {
            $day = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$day}";
            $result['date'] = $this->getNextMonthDay($day)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['monthly_day'], '', $input));
        } elseif (preg_match($patterns['every_n_years'], $input, $matches)) {
            $years = (int) $matches[1];
            $result['recurrence_pattern'] = "every {$years} years";
            $result['date'] = Carbon::today()->addYears($years)->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['every_n_years'], '', $input));
        } elseif (preg_match($patterns['yearly'], $input, $matches)) {
            $result['recurrence_pattern'] = 'yearly';
            $result['date'] = Carbon::today()->addYear()->format('Y-m-d');
            $result['name'] = trim(preg_replace($patterns['yearly'], '', $input));
        } elseif (preg_match($patterns['every_month_day'], $input, $matches)) {
            $month = $matches[1];
            $day   = (int) $matches[2];
            $result['recurrence_pattern'] = "every " . ucfirst(strtolower($month)) . " {$day}";
            $result['date'] = $this->getNextMonthDayDate($month, $day)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['every_month_day'], '', $input));
        } elseif (preg_match($patterns['date_month_day'], $input, $matches)) {
            $month = $matches[1];
            $day = (int) $matches[2];
            $result['date'] = $this->getNextMonthDayDate($month, $day)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['date_month_day'], '', $input));
        } elseif (preg_match($patterns['date_slash'], $input, $matches)) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $result['date'] = $this->getNextDate($month, $day)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['date_slash'], '', $input));
        } elseif (preg_match($patterns['date_iso'], $input, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $result['date'] = Carbon::create($year, $month, $day)->format('Y-m-d');
            $result['date_explicit'] = true;
            $result['name'] = trim(preg_replace($patterns['date_iso'], '', $input));
        }

        // Secondary pass: if a recurrence branch already fired but "today" or "tomorrow"
        // is still in the name, honour the explicit date override and strip the token.
        if ($result['recurrence_pattern'] !== null) {
            if (preg_match('/\btoday\b/i', $result['name'])) {
                $result['date'] = Carbon::today()->format('Y-m-d');
                $result['date_explicit'] = true;
                $result['name'] = trim(preg_replace('/\s+/', ' ',
                    preg_replace('/\btoday\b\s*/i', '', $result['name'])
                ));
            } elseif (preg_match('/\btomorrow\b/i', $result['name'])) {
                $result['date'] = Carbon::tomorrow()->format('Y-m-d');
                $result['date_explicit'] = true;
                $result['name'] = trim(preg_replace('/\s+/', ' ',
                    preg_replace('/\btomorrow\b\s*/i', '', $result['name'])
                ));
            }
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

    /**
     * After a date-only token (today/tomorrow) fires, check whether the remaining
     * name contains a recurrence pattern and extract it, preserving the already-set date.
     */
    protected function extractRecurrenceFromName(array $result, array $patterns): array
    {
        $name = $result['name'];

        // Try each recurrence pattern against the remaining name in priority order.
        // We intentionally skip date-only patterns (today/tomorrow/next X/dates) to
        // avoid re-parsing dates that were already stripped.
        $recurrenceChecks = [
            'daily'                 => fn($m) => ['daily', null],
            'weekdays'              => fn($m) => ['weekdays', null],
            'weekends'              => fn($m) => ['weekends', null],
            'every_other_day_literal' => fn($m) => ['every other day', null],
            'every_other_weekday'   => fn($m) => ["every other " . ucfirst(strtolower($m[1])), null],
            'every_other_week'      => fn($m) => ['every other week', null],
            'every_n_days'          => fn($m) => ["every {$m[1]} days", null],
            'every_n_months'        => fn($m) => ["every {$m[1]} months", null],
            'monthly'               => fn($m) => ['monthly', null],
            'weekly_literal'        => fn($m) => ['weekly', null],
            'every_n_weeks'         => fn($m) => ["every {$m[1]} weeks", null],
            'monthly_ordinal'       => fn($m) => [
                "every " . (['1st' => 'first', '2nd' => 'second', '3rd' => 'third', '4th' => 'fourth'][$m[1]] ?? strtolower($m[1])) . " {$m[2]}",
                null,
            ],
            'every_n_years'         => fn($m) => ["every {$m[1]} years", null],
            'yearly'                => fn($m) => ['yearly', null],
        ];

        foreach ($recurrenceChecks as $key => $builder) {
            if (preg_match($patterns[$key], $name, $m)) {
                [$recurrence] = $builder($m);
                $result['recurrence_pattern'] = $recurrence;
                $result['name'] = trim(preg_replace('/\s+/', ' ',
                    preg_replace($patterns[$key], '', $name)
                ));
                return $result;
            }
        }

        // Check for day-of-week recurrence (plural or "every X")
        if (preg_match_all($patterns['day_of_week'], $name, $allDayMatches)) {
            $dayName     = null;
            $isRecurring = false;
            foreach ($allDayMatches[1] as $idx => $captured) {
                $full   = $allDayMatches[0][$idx];
                $plural = strlen($full) > strlen($captured);
                $every  = (bool) preg_match('/\bevery\s+' . preg_quote($captured, '/') . '\b/i', $name);
                if ($plural || $every) {
                    $dayName     = ucfirst(strtolower($captured));
                    $isRecurring = true;
                    break;
                }
                $dayName = ucfirst(strtolower($captured));
            }
            if ($isRecurring && $dayName) {
                $result['recurrence_pattern'] = $dayName;
                $cap = preg_quote(strtolower($dayName), '/');
                $result['name'] = trim(preg_replace('/\s+/', ' ',
                    preg_replace('/\bevery\s+' . $cap . 's?\b\s*|\b' . $cap . 's?\b\s*/i', '', $name)
                ));
            }
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

    protected function getNextMonthDayDate(string $month, int $day, Carbon $currentDate = null): Carbon
    {
        $ref      = $currentDate ? $currentDate->copy() : Carbon::today();
        $monthNum = Carbon::parse($month . ' 1')->month;
        $date     = Carbon::create($ref->year, $monthNum, $day);

        if ($date->lte($ref)) {
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

        if (preg_match('/^every (\d+) years?$/', $normalizedPattern, $matches)) {
            $years = (int) $matches[1];
            return $currentDate->copy()->addYears($years);
        }

        if (preg_match('/^every (january|february|march|april|may|june|july|august|september|october|november|december) (\d{1,2})$/i', $normalizedPattern, $matches)) {
            $month = $matches[1];
            $day   = (int) $matches[2];
            return $this->getNextMonthDayDate($month, $day, $currentDate);
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
        // Unambiguous recurrence keywords — any occurrence signals a recurrence attempt
        $unambiguousKeywords = ['daily', 'weekly', 'monthly', 'yearly', 'weekdays', 'weekends'];
        foreach ($unambiguousKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $input)) {
                $parsed = $this->parseTaskInput($input);
                if ($parsed['recurrence_pattern'] !== null) {
                    return null;
                }
                return "The recurrence pattern in '{$input}' was not recognized. Supported patterns include: daily, every other day, weekdays, weekends, weekly, every other week, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, monthly, every month, every 3 months, every 1st (monthly), every first Monday (monthly), yearly, every 2 years, every January 3 (annual on specific date).";
            }
        }

        // "every" appears in plain English too ("backup every file"), so only treat it as a
        // recurrence attempt when it is immediately followed by a recurrence-vocabulary word.
        if (!preg_match('/\bevery\b/i', $input)) {
            return null;
        }

        // Extract the immediate next word after "every"
        if (!preg_match('/\bevery\s+(\S+)/i', $input, $m)) {
            // "every" at end of string — plain English, leave it alone
            return null;
        }
        $nextWord = strtolower(rtrim($m[1], '.,;:!?'));

        $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $monthNames = ['january', 'february', 'march', 'april', 'may', 'june',
                       'july', 'august', 'september', 'october', 'november', 'december'];
        $recurrenceVocab = array_merge($dayNames, $monthNames, [
            'other', 'day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years',
            'first', 'second', 'third', 'fourth', 'last',
            '1st', '2nd', '3rd', '4th',
        ]);
        $isNumeric = (bool) preg_match('/^\d{1,2}(st|nd|rd|th)?$/', $nextWord);

        if (!in_array($nextWord, $recurrenceVocab, true) && !$isNumeric) {
            // Not a recurrence-vocabulary word — plain English, leave it alone
            return null;
        }

        // Looks like a recurrence attempt — check if it actually parsed
        $parsed = $this->parseTaskInput($input);
        if ($parsed['recurrence_pattern'] !== null) {
            return null;
        }

        return "The recurrence pattern in '{$input}' was not recognized. Supported patterns include: daily, every other day, weekdays, weekends, weekly, every other week, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, monthly, every month, every 3 months, every 1st (monthly), every first Monday (monthly), yearly, every 2 years, every January 3 (annual on specific date).";
    }
}
