# Repository Review: Improvements, Features, and Security

## Quick strengths observed
- Good separation between controllers, jobs, services, and policies.
- Authorization is consistently enforced in ticket actions (`$this->authorize('view', $ticket)`).
- Login rate limiting is enabled in the authentication request layer.
- PII redaction service exists before AI processing.

## High-impact improvements (engineering)
1. **Extract ticket business flows into service classes**
   - `TicketController` is doing orchestration + domain logic for tags, comments, status, and ordering.
   - Move logic into dedicated services (e.g., `TicketTagService`, `TicketCommentService`, `TicketWorkflowService`) to reduce controller complexity and improve testability.

2. **Improve query performance in ticket list filters**
   - Current list-building performs `pluck()->unique()` flows for organizations/requesters from the visible base query.
   - Consider moving to optimized joins/subqueries and adding indexes for frequently filtered columns (`status`, `priority`, `org_id`, `requester_id`, `zd_updated_at`).

3. **Add request classes for mutating ticket endpoints**
   - Endpoints like `updateStatus`, `updatePendingAction`, `storeComment`, `updateTags` could use dedicated Form Requests.
   - This centralizes validation and authorization and simplifies controllers.

4. **Expand automated test coverage**
   - Add feature tests for role-based authorization matrix (`admin`, `colaborador`, `cliente`) for all ticket mutations.
   - Add tests for sync commands and fallback email generation.

## Product feature ideas
1. **SLA risk prediction and queue surfacing**
   - Build a score combining ticket age, priority, severity, and sentiment to show "at-risk" tickets.

2. **Agent productivity dashboard**
   - Metrics per org/agent: first response time, resolution time, reopen rate, top ticket categories, AI suggestion acceptance rate.

3. **Bulk operations on filtered ticket sets**
   - Apply tags/status/internal effort for selected/filtered tickets with background jobs.

4. **Workflow automations**
   - Rules such as: when pending action is `customer_side` for X days, suggest follow-up or closure task.

## Security review and recommendations

### ✅ Positive controls
- Rate limiting on login attempts is implemented.
- Session regeneration after successful login is in place.
- Ticket access is protected by `ZdTicketPolicy` and role checks.

### ⚠️ Findings (with recommendations)
1. **Default weak temporary password risk in user sync command**
   - Risk: predictable credentials (`changeme`) can cause account compromise if used in production.
   - Fix applied: random strong temporary password generation when no password is provided, and password output is hidden by default unless `--show-password` is explicitly set.

2. **Credential leakage through command output**
   - Risk: printing temporary passwords to logs/shell history exposes secrets.
   - Fix applied: command no longer prints the password by default.

3. **Potential information disclosure in user-facing error messages**
   - Some Zendesk exception messages are sent directly to flash errors.
   - Recommendation: log raw exception server-side and show sanitized user-facing messages.

4. **Attachment scanning and content enforcement**
   - Attachments have size checks, but there is no antivirus/content disarm pipeline.
   - Recommendation: integrate malware scanning (ClamAV or cloud scanning service) before upload relay.

5. **Hardening recommendations**
   - Add CSP and security headers via middleware (`X-Frame-Options`, `X-Content-Type-Options`, strict `Referrer-Policy`).
   - Enforce secure cookie/session flags in production (`secure`, `http_only`, `same_site=strict/lax` as needed).
   - Add audit logs for privileged actions (status/tag updates, admin updates).

## Prioritized next steps
1. Add/expand tests for authorization and ticket mutation endpoints.
2. Refactor controller logic into services and Form Requests.
3. Implement sanitized error handling strategy for external API failures.
4. Add security header middleware and attachment scanning.
