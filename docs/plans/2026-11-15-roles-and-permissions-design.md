# Users, roles and permissions: design

Date: 15 Nov 2026 · Status: approved and built (15 Nov 2026)

Built as designed, with three things settled while building:

- **Home page:** the dashboard needs "View dashboard and reports". Anyone without it lands on the first page they can open, and someone with no pages gets a clear 403.
- **Sidebar and Master data tabs:** they show only the pages the person can open.
- **Error box in drawer forms:** a business-rule error (such as "there must always be an active owner") now shows at the top of the form. It was silently lost before, because the box failed to build.

## Why

The owner finds "Users & roles" confusing, for two reasons:

1. **The screen is hard to read.** The "Role permissions" tab is a static table that only describes rules hard-coded in the routes. Roles, distributor limits and the medical-rep link are all mixed together on the same screen.
2. **It can't express what the business needs.** There are five fixed roles written into the code (Owner, Accounts, Data entry, Viewer, Medical rep). The owner can't add a role, and can't give one person a single extra ability.

Success means the owner can:

- create, rename and delete roles;
- tick a role's permissions;
- add extra permissions to one person;
- read on a single screen exactly what any person can do and which data they see.

## Goals

- Custom roles, managed in the portal.
- A fixed list of permissions, grouped by area. Roles get permissions by ticking them.
- Extra permissions on a single person, on top of their role. **Add only**: the owner's decision.
- Every access check in the portal reads permissions instead of role names. That covers web routes, form requests, buttons in views, sidebar links and the API.
- On release day, everyone keeps exactly the access they have today.

## Non-goals

- Taking a permission away from one person. To give someone less than their role, make a smaller role.
- Several roles per person. Each person has exactly one role.
- Permissions the owner can create in the portal. Each permission guards code, so the list changes only with the code.
- Changes to how distributor limits or the medical-rep data scope work.

## Anti-goals (it fails if…)

- Anyone gains or loses access on release day.
- The owner can lock themselves out, or no active Owner is left.
- A person with "Manage users and roles" can give themselves or others more than they have.
- A role's permissions, the extras and the data limits can't be seen together for one person.

## Approaches considered

- **A. Own tables and Laravel's built-in permission checks.** Chosen, as recommended; the owner asked for the recommended option.
  - Permissions are a PHP enum, like the project's other fixed lists (ImportStatus, PaymentMethod…).
  - Roles and the two link tables are in the database.
  - No new library.
- **B. The `spatie/laravel-permission` package.**
  - It's standard and supports exactly this.
  - Against it:
    - its permission rows in the database have to be kept in step with the code;
    - it allows several roles per person, which isn't wanted here;
    - it's a new dependency.
- **C. Fixed roles with a matrix the owner can edit.** Dropped: the owner needs custom roles.

## Permissions (fixed list, 27)

| Area | Key | Label |
|---|---|---|
| Dashboard & reports | `dashboard.view` | View dashboard and reports |
| Imports | `imports.view` | View imports |
| | `imports.upload` | Upload, check and discard files (includes linking product names in review) |
| | `imports.confirm` | Confirm an import |
| | `imports.revert` | Revert an import |
| Customers | `customers.view` | View customers |
| | `customers.assign` | Assign reps to customers |
| Distributors | `distributors.view` | View distributors and name matching |
| | `distributors.edit` | Edit distributor profile |
| | `distributors.unlink` | Unlink product names |
| Medical reps | `reps.view` | View reps |
| | `reps.edit` | Add and edit reps |
| Products | `products.view` | View products |
| | `products.rates` | Change product rates |
| Stock | `stock.view` | View inventory (includes the stock-file template download) |
| | `stock.receive` | Post a stock receipt |
| | `stock.revert` | Revert a stock receipt |
| Ledger | `ledger.view` | View and export ledger |
| | `ledger.record` | Record a payment |
| | `ledger.remove` | Remove a payment |
| Invoices | `invoices.view` | View and print invoices |
| | `invoices.issue` | Issue an invoice |
| | `invoices.cancel` | Cancel an invoice |
| Payroll | `payroll.view` | View payroll (also shows payroll money on the dashboard) |
| | `payroll.adjust` | Adjust payroll |
| Admin | `activity.view` | View activity log |
| | `users.manage` | Manage users and roles |

**Dependencies:** each action needs its area's "view" permission. Ticking an action ticks its "view" too. Unticking a "view" unticks the area's actions. The server enforces the same rule when saving.

## Mapping from today (seeded roles)

Each permission below was derived from the current `role:` middleware and `hasRole()` calls.

| Role | Permissions |
|---|---|
| **Owner** (protected) | All, always (the check passes automatically, so the role stores no permissions) |
| **Accounts** | dashboard.view · imports.view, upload, confirm · customers.view, assign · distributors.view · reps.view, edit · products.view · stock.view, receive, revert · ledger.view, record · invoices.view, issue · payroll.view, adjust · activity.view |
| **Data entry** | dashboard.view · imports.view, upload · customers.view, assign · distributors.view · reps.view · products.view · stock.view |
| **Viewer** | dashboard.view · imports.view · customers.view · distributors.view · reps.view · products.view · stock.view · ledger.view |

The builder verifies this table against every route and every `hasRole()` call before migrating. Any difference found is a bug in this table, not a licence to change someone's access.

A known quirk to keep: the API route `PUT customers/{customer}/reps` allows Accounts only, while the web route also allows Data entry. With one permission (`customers.assign`), Data entry gains it on the API. *My call:* accept this, because the web screen already allows it.

## Medical reps are a login type, not a role

*My call.* A medical rep login sees only their own data (My territory), whatever permissions say. Because of that, the person form starts with **Login type: Staff / Medical rep**.

- **Staff:**
  - a role;
  - optional extra permissions;
  - optional "Distributors they can see" (empty means all).
- **Medical rep:**
  - pick the rep record;
  - no role, no permissions, no distributor limit;
  - lands on My territory and can open only My territory and their own payslip, as today.

The existing Medical rep role disappears from the roles list. Its users migrate to login type Medical rep.

## Rules

- **Owner role:**
  - it can't be renamed, edited or deleted;
  - it ignores extras and distributor limits.
- **The last active Owner** can't be deactivated, changed to another role or switched to Medical rep. Today's "you cannot remove your own owner access" rule becomes this broader rule.
- **Only an Owner** can make someone an Owner.
- **No escalation:** a non-Owner with `users.manage` can give a role or person only permissions they hold themselves. They also can't edit the Owner role or Owner users.
- **Deleting a role** is blocked while anyone has it. The message names how many people do.
- **Role names** must be unique, at most 50 characters. A role has an optional one-line description.
- **Changes take effect on the person's next page load.** There's no forced sign-out.
- **Activity log:** add, rename or delete a role; change a role's permissions (which were added and which removed); change a person's role, extras, login type or limits.

## Screens

The menu item stays "Users & roles" and becomes two tabs: **People** and **Roles**. Both open their forms in the existing right-hand drawer.

**People tab:** a table showing name, login, login type / role, extra permissions (count), data limit and status.

**Person drawer**, top to bottom:

1. Name, email, password (password only when adding), active.
2. Login type (Staff / Medical rep).
3. **Staff only:**
   - Role (select).
   - **"What they can do":** every permission, grouped by area.
     - Permissions from the role are shown ticked and disabled, marked "from role".
     - The others are normal checkboxes for extras.
     - Changing the role updates the greyed ticks immediately, without saving.
   - **"Which data they see":** distributor limit.
4. **Medical rep only:** the rep record.

**Roles tab:** a list of roles with their description, how many people have each and how many permissions. There's an "Add role" button.

**Role drawer:**

- name and description;
- permissions grouped by area, with a "select all" per area.

A role's people are listed read-only at the bottom.

## Technical shape

- **Enum** `App\Modules\Access\Enums\Permission`, a string-backed enum using `HasOptions`. It provides `label()`, `group()` and `requires()` (its "view" permission).
- **Tables:**
  - `roles`: id, name unique, description nullable, is_owner boolean, timestamps;
  - `role_permission`: role_id, permission string, primary key on both;
  - `user_permission`: user_id, permission string, primary key on both.
- **`users` table changes:**
  - add `role_id` (nullable FK, null for medical reps);
  - add `login_type` (`staff` | `medical_rep`).
- **Checks:**
  - `User::hasPermission(Permission)` is memoised for the request. It returns true for an Owner, or when the permission is in the role or in the extras.
  - `Gate::before` maps every `Permission` key to it, so `can:imports.confirm` middleware, `@can` in Blade and `$user->can()` all work.
  - Medical-rep logins fail every permission.
  - Replace these with permission checks:
    - `role:` middleware: the `EnsureRole` alias is removed;
    - `hasRole()`: removed;
    - `FormRequest::$roles`: renamed `$permission`;
    - `role === Role::MedicalRep` checks: replaced with `isMedicalRep()`;
    - the sidebar's `$can(Role…)`.
- **API:** the same checks apply. API tokens keep working; their access follows the person's current permissions.
- **Migration** (one migration, reversible):
  1. Create the tables.
  2. Seed Owner, Accounts, Data entry and Viewer with the mapping above.
  3. Set `role_id` and `login_type` from the old `role` column; medical_rep users get login type Medical rep.
  4. Drop `role`. The down migration restores `role` from `role_id` / `login_type`.
- **`DatabaseSeeder`:** seeds the same roles and makes the install owner an Owner.

## Tests

- A permission check passes through the role, through an extra, and always for an Owner. Medical reps fail every check.
- For each seeded role, a sample of routes returns the same allow or deny as before the change. Write this test first, against the current code, so it proves nothing changed.
- No escalation: a non-Owner manager can't grant a permission they don't have, can't edit the Owner role, and can't create an Owner.
- The last active Owner can't be demoted or deactivated.
- A role in use can't be deleted.
- Dependency rule: saving an action without its "view" permission stores both.
- The migration runs up and down, keeping users' access.

## Left to the builder

- The exact file and folder names inside `app/Modules/Access`.
- The visual details of the drawers, within the existing Skyline styles and drawer, toast and confirm components.
- Whether role permissions are saved as one set-replace or as a diff, as long as the activity log records the diff.
