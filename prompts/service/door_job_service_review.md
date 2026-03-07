
# DoorJobService Review Prompt

Review DoorJobService for the Community Offers project.

Context:

DoorJobService manages the lifecycle of door opening commands.

Lifecycle:
create → pending → confirmed

Check:

1. Responsibility
- Is the service focused on job lifecycle?

2. Job Creation
- Are area, member, nonce, and status correctly initialized?

3. Poll Logic
- How are pending jobs returned?
- Are only valid jobs returned?

4. Confirm Logic
- Is nonce verification correct?
- What happens if:
  - job ID does not exist
  - nonce is invalid
  - job already confirmed

5. Invariants
- Are illegal states prevented?

6. Logging
- Are important state transitions logged?

7. Tests
- Which edge cases should be tested?

Respond with:

A. Summary  
B. Method explanations  
C. Lifecycle invariants  
D. Risks  
E. Missing tests  
F. Related files  
G. Refactoring suggestions
