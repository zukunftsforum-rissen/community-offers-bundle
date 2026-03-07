
# DeviceController Review Prompt

Review DeviceController for the Community Offers project.

Project context:

- Raspberry Pi uses a poll model
- Backend creates DoorJobs
- Device polls for pending jobs
- Device confirms execution with nonce

Check:

1. Responsibility
- Is the controller only coordinating HTTP requests?
- Is business logic delegated to services?

2. Poll Flow
- Are pending jobs correctly returned?
- Is the response structure clear?

3. Confirm Flow
- Is nonce validation implemented correctly?
- What happens if:
  - job does not exist
  - nonce is wrong
  - job already confirmed

4. Architecture
- Does the controller remain thin?
- Should logic be moved to DoorJobService?

5. Logging
- Is LoggingService used correctly?

6. Tests
- Which tests should exist?

Respond with:

A. File summary  
B. Section explanation  
C. Architecture alignment  
D. Risks  
E. Missing tests  
F. Related files  
G. Suggested improvements
