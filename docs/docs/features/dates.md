## Assigned date
Assigned dates are always in the future.

You can assign a date in any of the following formats:
- Day of week such as Sunday or Sun
- yyyy-mm-dd such has 2026-01-24
- Human date such has January 24

If you'd like to refer to a specific day not as the date, you'll have to specify a date or `nodate`. The second date is the one it will assign it to. For example, "Reply to the letter that came on Sunday Tuesday" will become a task assigned to Tuesday with the text "Reply to the letter that came on Sunday"

## Recurrences
To make a task recurring, you can say "every [interval]" or "!every [interval]". The leading exclamation point indicates that you want it to be floating. 
    - If you put "every week" on Tuesday, the next task will be assigned to Tuesday. If you complete it on Wednesday, the following task will still be on Tuesday. 
    - For "!every week", the task will repeat one week after it's taken place. So if you complete the task on Wednesday, the new one will be created on Wednesday.

### Frequencies supported 
- every day 
- every week
- every month
- every year
- every [date in any format accepted above] - such as every Sun or every January 24.  

