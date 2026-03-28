# Access Request Sequence Diagrams

This document visualizes the two distinct access request workflows:

- Initial Access Request
- Additional Access Request

These workflows must remain clearly separated.

---

## 1) Initial Access Request (new member)

```plantuml
@startuml
actor Applicant
participant "Access Request Form" as Form
participant "AccessRequestService" as Service
participant "Database\ntl_co_access_request" as RequestDB
participant "Mail / DOI Link" as Mail
participant "AccessConfirmController" as Confirm
participant "Member Creation Logic" as MemberCreate
participant "Database\ntl_member" as MemberDB
participant "Password Setup" as Password
participant "Admin" as Admin

Applicant -> Form : fill and submit form
Form -> Service : create initial access request
Service -> RequestDB : store request
Service -> Mail : send DOI mail

Applicant -> Mail : click DOI link
Mail -> Confirm : confirm token
Confirm -> MemberCreate : create member
MemberCreate -> MemberDB : insert tl_member
Confirm -> Password : start password setup

Applicant -> Password : set password
Admin -> Confirm : approve request
Confirm -> MemberDB : enable member / assign rights
@enduml
```

---

## 2) Additional Access Request (existing member)

```plantuml
@startuml
actor Member
participant "App" as App
participant "AccessRequestService" as Service
participant "Database\ntl_co_access_request" as RequestDB
participant "Mail / DOI Link" as Mail
participant "AccessConfirmController" as Confirm
participant "AccessService" as AccessService
participant "Database\ntl_member_group / tl_member" as MemberDB
participant "Admin" as Admin

Member -> App : click request for additional access
App -> Service : create additional access request
Service -> RequestDB : store request
Service -> Mail : send DOI mail

Member -> Mail : click DOI link
Mail -> Confirm : confirm token

Admin -> Confirm : approve request
Confirm -> AccessService : resolve groups / rights
AccessService -> MemberDB : update existing member permissions
@enduml
```

---

## 3) Key Differences

| Aspect | Initial Access Request | Additional Access Request |
|--------|------------------------|---------------------------|
| Existing member required | No | Yes |
| Input source | Form | App click |
| Creates tl_member | Yes | No |
| Password step | Yes | No |
| Result | New member | Additional permissions |
| Admin approval required | Yes | Yes |

---

## 4) Domain Rule

These workflows must never be merged.

Important constraints:

- Initial requests create new members
- Additional requests never create members
- Additional requests never trigger password setup
- Permission updates must target existing members only
