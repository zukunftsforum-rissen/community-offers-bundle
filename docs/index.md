# Community Offers Bundle – Documentation

Open-source access control system for shared community spaces.

---

# System Principle

Core execution flow:

member open request  
        ↓  
door job created (pending)  
        ↓  
device poll  
        ↓  
job dispatched  
        ↓  
device confirm  
        ↓  
executed / failed / expired  

---

# Access Request Types

The system supports two fundamentally different access request workflows.

These workflows must remain clearly separated.

## Initial Access Request (First Application)

Used when:

A person does not yet have a member account.

Typical flow:

Form  
→ DOI email  
→ Member creation  
→ Password setup  
→ Admin approval  

Result:

→ new member created

Key characteristics:

- Requires form input  
- Requires password setup  
- Creates new member  
- Login enabled only after admin approval  

---

## Additional Access Request (Additional Rights)

Used when:

A member already exists.

Typical flow:

App click  
→ DOI email  
→ Admin approval  
→ Permission update  

Result:

→ existing member receives additional permissions

Key characteristics:

- No form required  
- No password step  
- No new member created  
- Only permissions updated  

---

# Runtime Modes

The system supports two runtime modes.

## Live Mode

Used in production with real hardware.

Device:

Raspberry Pi with relay control.

## Emulator Mode

Used for testing and diagnostics.

Device behavior is simulated.

No physical hardware required.
