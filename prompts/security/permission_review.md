
# Permission Logic Review Prompt

Review the permission logic for the Community Offers project.

Domain rules:

Areas:
- workshop → members age 18+
- sharing → all members
- swap-house → all members
- depot → members with crate subscription

Check:

1. Domain rules
- Do implemented rules match the domain model?

2. Completeness
- Are all areas handled?

3. Error cases
- unknown area
- missing member
- inconsistent member data

4. Security
- Can access checks be bypassed?
- Are parameters validated server-side?

5. Architecture
- Is permission logic centralized?

6. Tests

Expected cases:

workshop:
- adult allowed
- underage denied

depot:
- with crate subscription allowed
- without denied

sharing:
- member allowed

swap-house:
- member allowed

Respond with:

A. Implemented permission rules  
B. Domain-model comparison  
C. Security risks  
D. Missing edge cases  
E. Missing tests  
F. Improvement suggestions  
G. Related files
