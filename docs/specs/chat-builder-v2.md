# Module Specification — Conversation builder v2 (CHAT, builder)

Status: in build (2026-09-22). Extends the Website Chat module (CHAT-007..012).

## Purpose

Make building a website-chat conversation easy enough that a business owner does it
without help. The v1 builder is a flat list of steps wired together with "Then go to"
dropdowns: the owner has to hold the whole graph in their head, a new step starts
disconnected, contact fields need internal names typed in ("first_name"), and choosing
a template replaces a live conversation on the spot. v2 replaces the wiring with a tree
that reads top to bottom and is built by dragging ready-made blocks into place.

## Comparison (what the leading tools do)

| | Layout | Adding a step | Contact fields | Templates |
|---|---|---|---|---|
| HubSpot chatflows | Vertical list, branches indented | "+" between actions, then pick a type | Question tied to a contact property, validation per type | By goal (qualify, meetings, tickets, concierge) |
| Intercom Workflows | Free canvas with paths | Drag from a block list, connect paths | "Collect data" block per attribute, required toggle | Library by use case |
| Drift Playbooks | Visual canvas | Add from a menu, connect | Email capture blocks | By goal |
| Tidio Flows | Node canvas | Drag node, connect lines | Ask for email/phone nodes | 30+ by goal and industry |
| Landbot | Node canvas | Drag blocks from a library | Dedicated Name/Email/Phone blocks with validation | Gallery by industry and use case |
| Typebot | Groups on a canvas | Drag blocks from a sidebar | Email/Phone/Number input blocks | Gallery |

What makes the easy ones easy: ready-made blocks for common fields (no field names to
type), adding a step exactly where it goes (no wiring), branches you can see, and a
template gallery organised by type of business. What makes canvases hard for owners:
free-floating nodes and connector lines ("spaghetti") on anything bigger than a few steps.

**Decision:** a top-to-bottom tree (HubSpot's readability) built by drag-and-drop from a
block library (Landbot/Typebot's ease), with ready contact blocks and a Required/Optional
switch on each, branches drawn under the question that creates them and re-joining below,
a "+" in every gap as the non-drag alternative, undo/redo, and templates by business type.

## Users & roles

Widget managers (`chat.widget.manage`) build and publish; others cannot open the builder.

## Feature IDs

CHAT-052 Tree conversation builder · CHAT-053 Drag-and-drop block library ·
CHAT-054 Contact blocks with required/optional · CHAT-055 Business template gallery ·
CHAT-056 Builder undo/redo · CHAT-057 Start a new widget from a template ·
CHAT-058 Canvas builder layout (the owner's reference design) · CHAT-059 Step settings
with Quick Replies · CHAT-060 Hideable panels and focus mode · CHAT-061 Edit steps on the
canvas · CHAT-062 Pick-and-place and wide drop areas · CHAT-063 Managing steps on the canvas.

## User stories

- As an owner I drag "Email" under "What would you like to explore?" and it is asked there.
- As an owner I mark Phone optional so visitors can skip it, and Email required.
- As an owner I pick "Software / SaaS" from the templates, see its steps first, and
  publish it after changing two answers.
- As an owner I see each answer's path under the question and where paths meet again.
- As an owner I undo a mistaken delete.

## Subscription requirements

Unchanged: the chat feature (entitlement `chat`).

## Database entities

None. The flow stays the same JSON graph (`chat_widgets.flow`); the tree is a way of
drawing and editing it. Every existing conversation opens in the new builder unchanged.

## API endpoints

- `GET chat/widgets/{widget}/flow` — the catalog now carries each template's category
  and flow (for preview and loading into the editor without saving).
- `POST chat/widgets` — optional `template` key starts the new widget from a template.
- Existing save/publish/validate/test/template endpoints unchanged.

## UI pages & components

Builder page, laid out after the owner's reference: Steps | Templates panel with search
(left); a dotted canvas (centre) with a Start card, tinted step cards with a ⋯ menu,
answers fanning out sideways under labelled pills and converging where they meet, zoom,
fit, full screen and a mini map; Step Settings (right) with Content / Advanced / Condition
tabs and a Quick Replies switch. Header: widget switcher, undo/redo, Save, Test, Publish,
menu. A shortcut strip below. Template gallery dialog with categories, preview and
confirmation. Widgets page: "Start from" choice when creating a widget.

Usability pass: the Steps panel folds to an icon strip and the Settings panel hides
(remembered per browser); Focus mode gives the builder the whole window. Text and replies
are edited on the cards. A step is placed by dragging it or by clicking it and then a
highlighted place; allowed places open into wide targets with a preview. Card menu: Move
to…, Duplicate, Fold its paths away. Keys: Ctrl+Z / Ctrl+Shift+Z, Delete, Esc.

## Business rules & validation

- Dropping a block into a gap links it in: the step that led to the gap now leads to the
  new block, and the new block leads on to whatever came next. A question's answers all
  lead on; a Finish block may only be placed where nothing follows.
- Deleting a step reconnects its neighbours; deleting a question also removes the steps
  that only its answers led to, after a confirmation naming how many.
- Contact blocks save to the CRM fields the capture service reads (first_name, last_name,
  email, phone, company_name); Email and First name default to required.
- Loading a template changes the editor only; nothing reaches visitors until Publish.
- Server-side validation is unchanged and still gates publishing.

## Error cases

Invalid drop targets refuse the drop visibly. Validation errors show on the step card
they concern as well as in the banner.

## Audit requirements

Unchanged (`chat.flow.saved` / `chat.flow.published` / `chat.widget.created`).

## Automated tests

Vitest: tree building (linear, branching, re-joining, jumps, loops), insert/move/delete
with reconnection, required toggles, template loading. Pest: every template valid with
no warnings, categorised, and asking for an email; widget creation from a template.

## Acceptance criteria

A new owner can build "welcome → question → name/email (required) → phone (optional) →
book a meeting" by dragging blocks, without typing a field name or choosing a "Then go
to", test it, and publish it.
