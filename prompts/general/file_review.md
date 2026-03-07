
# General File Review Prompt

Review this file for the Community Offers project.

Check the following aspects:

1. What does this file do technically and functionally?
2. Does it match the architecture defined in:
   - docs/ai/architecture.md
   - docs/ai/domain-model.md
   - docs/ai/api-flows.md
3. Is business logic placed correctly in services instead of controllers?
4. Are there robustness or security issues?
5. Which error cases are not handled?
6. Which tests are missing?
7. Which parts are unnecessarily complex or unclear?
8. Which other files should I read to fully understand this file?

Respond in this structure:

A. Short description  
B. Section-by-section explanation  
C. Architecture comparison  
D. Risks / weaknesses  
E. Missing tests  
F. Related files  
G. Concrete improvement suggestions

Important:
- Distinguish between confirmed findings and assumptions.
- Do not invent classes, routes, or services.
