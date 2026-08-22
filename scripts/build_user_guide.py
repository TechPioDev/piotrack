#!/usr/bin/env python3
"""Generate the Piotrack User Guide PDF (branded, example-driven)."""

from reportlab.lib.pagesizes import LETTER
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, ListFlowable, ListItem, KeepTogether, HRFlowable, NextPageTemplate,
)
import sys

OUT = sys.argv[1] if len(sys.argv) > 1 else "piotrack-user-guide.pdf"

# ---- Brand palette ----
TEAL      = colors.HexColor("#0BB39E")
TEAL_DEEP = colors.HexColor("#0A7A6C")
TEAL_SOFT = colors.HexColor("#E6F7F3")
CORAL     = colors.HexColor("#FF6B54")
AMBER     = colors.HexColor("#F5A623")
INK       = colors.HexColor("#0B1A23")
MUTED     = colors.HexColor("#5B7480")
LINE      = colors.HexColor("#DBE8E8")
BAND      = colors.HexColor("#0B1A23")
WHITE     = colors.white

# ---- Styles ----
ss = getSampleStyleSheet()
def style(name, **kw):
    return ParagraphStyle(name, parent=ss["Normal"], **kw)

BODY   = style("Body", fontName="Helvetica", fontSize=10.5, leading=15.5, textColor=INK, spaceAfter=7)
LEAD   = style("Lead", fontName="Helvetica", fontSize=12, leading=17, textColor=INK, spaceAfter=9)
H1     = style("H1", fontName="Helvetica-Bold", fontSize=18, leading=22, textColor=TEAL_DEEP, spaceBefore=6, spaceAfter=4)
H1NUM  = style("H1num", fontName="Helvetica-Bold", fontSize=11, leading=13, textColor=TEAL, spaceAfter=1)
H2     = style("H2", fontName="Helvetica-Bold", fontSize=12.5, leading=16, textColor=INK, spaceBefore=10, spaceAfter=3)
BULLET = style("Bullet", fontName="Helvetica", fontSize=10.5, leading=15, textColor=INK)
STEP   = style("Step", fontName="Helvetica", fontSize=10.5, leading=15, textColor=INK)
EXHEAD = style("ExHead", fontName="Helvetica-Bold", fontSize=9, leading=12, textColor=TEAL_DEEP)
EXBODY = style("ExBody", fontName="Helvetica", fontSize=10, leading=14.5, textColor=INK)
NOTE   = style("Note", fontName="Helvetica", fontSize=9.5, leading=13.5, textColor=colors.HexColor("#8A5A00"))
MONO   = style("Mono", fontName="Courier", fontSize=9.5, leading=13, textColor=TEAL_DEEP)
CAP    = style("Cap", fontName="Helvetica", fontSize=9, leading=12, textColor=MUTED)
TOCITEM= style("Toc", fontName="Helvetica", fontSize=10.5, leading=17, textColor=INK)
COVERT = style("CoverTitle", fontName="Helvetica-Bold", fontSize=34, leading=38, textColor=WHITE)
COVERS = style("CoverSub", fontName="Helvetica", fontSize=13, leading=18, textColor=colors.HexColor("#Bfeee7"))

def bullets(items):
    return ListFlowable(
        [ListItem(Paragraph(t, BULLET), leftIndent=6, value="•", spaceb=3) for t in items],
        bulletType="bullet", bulletColor=TEAL, bulletFontSize=8, leftIndent=14, spaceBefore=1, spaceAfter=6,
    )

def steps(items):
    return ListFlowable(
        [ListItem(Paragraph(t, STEP), leftIndent=6, spaceb=3) for t in items],
        bulletType="1", bulletColor=TEAL_DEEP, bulletFontName="Helvetica-Bold", bulletFontSize=10,
        leftIndent=16, spaceBefore=1, spaceAfter=6,
    )

def example(title, lines):
    body = [Paragraph(("EXAMPLE  •  " + title).upper(), EXHEAD), Spacer(1, 3)]
    for ln in lines:
        body.append(Paragraph(ln, EXBODY))
    inner = Table([[body]], colWidths=[6.0 * inch])
    inner.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), TEAL_SOFT),
        ("LEFTPADDING", (0, 0), (-1, -1), 14),
        ("RIGHTPADDING", (0, 0), (-1, -1), 14),
        ("TOPPADDING", (0, 0), (-1, -1), 11),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 11),
        ("LINEBEFORE", (0, 0), (0, -1), 3, TEAL),
        ("ROUNDEDCORNERS", [6, 6, 6, 6]),
    ]))
    return KeepTogether([Spacer(1, 2), inner, Spacer(1, 8)])

def note(text):
    inner = Table([[Paragraph("<b>Good to know:</b> " + text, NOTE)]], colWidths=[6.0 * inch])
    inner.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FFF6E6")),
        ("LEFTPADDING", (0, 0), (-1, -1), 12), ("RIGHTPADDING", (0, 0), (-1, -1), 12),
        ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
        ("LINEBEFORE", (0, 0), (0, -1), 3, AMBER),
    ]))
    return KeepTogether([Spacer(1, 2), inner, Spacer(1, 8)])

def section(num, title):
    return KeepTogether([
        Spacer(1, 6),
        Paragraph("SECTION %02d" % num, H1NUM),
        Paragraph(title, H1),
        HRFlowable(width="100%", thickness=1, color=LINE, spaceBefore=4, spaceAfter=8),
    ])

# ---- Page furniture ----
def cover(canvas, doc):
    canvas.saveState()
    w, h = LETTER
    canvas.setFillColor(BAND)
    canvas.rect(0, 0, w, h, fill=1, stroke=0)
    # accent blobs
    canvas.setFillColor(TEAL)
    canvas.circle(w - 0.7 * inch, h - 1.0 * inch, 46, fill=1, stroke=0)
    canvas.setFillColor(CORAL)
    canvas.circle(w - 1.35 * inch, h - 1.35 * inch, 20, fill=1, stroke=0)
    # brand mark + wordmark
    canvas.setFillColor(TEAL)
    canvas.roundRect(0.9 * inch, h - 1.5 * inch, 34, 34, 8, fill=1, stroke=0)
    canvas.setStrokeColor(WHITE)
    canvas.setLineWidth(2.4)
    x0, y0 = 0.9 * inch + 8, h - 1.5 * inch + 12
    canvas.line(x0, y0, x0 + 6, y0 + 6); canvas.line(x0 + 6, y0 + 6, x0 + 10, y0 + 3); canvas.line(x0 + 10, y0 + 3, x0 + 18, y0 + 11)
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 15)
    canvas.drawString(0.9 * inch + 44, h - 1.5 * inch + 11, "Piotrack")
    canvas.setFillColor(colors.HexColor("#7fdfd0"))
    canvas.setFont("Helvetica", 10)
    canvas.drawString(0.9 * inch, h - 4.0 * inch + 96, "THE GROWTH OPERATING SYSTEM FOR MSPs")
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 40)
    canvas.drawString(0.9 * inch, h - 4.0 * inch + 40, "User Guide")
    canvas.setFont("Helvetica", 15)
    canvas.setFillColor(colors.HexColor("#Bfeee7"))
    canvas.drawString(0.9 * inch, h - 4.0 * inch + 12, "A to Z, in plain English, with worked examples.")
    canvas.setFont("Helvetica", 10)
    canvas.setFillColor(colors.HexColor("#8fb4b0"))
    canvas.drawString(0.9 * inch, 0.8 * inch, "Get more clients for your managed-IT business - marketing, sales, SEO, ads and AI in one place.")
    canvas.restoreState()

def later(canvas, doc):
    canvas.saveState()
    w, h = LETTER
    canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(0.9 * inch, 0.72 * inch, w - 0.9 * inch, 0.72 * inch)
    canvas.setFont("Helvetica", 8.5); canvas.setFillColor(MUTED)
    canvas.drawString(0.9 * inch, 0.55 * inch, "Piotrack - User Guide")
    canvas.drawRightString(w - 0.9 * inch, 0.55 * inch, "Page %d" % (doc.page - 1))
    canvas.setFont("Helvetica-Bold", 8.5); canvas.setFillColor(TEAL_DEEP)
    canvas.drawCentredString(w / 2.0, 0.55 * inch, "piotrack")
    canvas.restoreState()

doc = BaseDocTemplate(OUT, pagesize=LETTER,
                      leftMargin=0.9 * inch, rightMargin=0.9 * inch,
                      topMargin=0.9 * inch, bottomMargin=0.9 * inch,
                      title="Piotrack User Guide", author="Piotrack")
frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="main")
doc.addPageTemplates([
    PageTemplate(id="cover", frames=[frame], onPage=cover),
    PageTemplate(id="body", frames=[frame], onPage=later),
])

S = []
def P(t, st=BODY): S.append(Paragraph(t, st))
def SP(x=6): S.append(Spacer(1, x))

# ===== COVER =====
# Page 1 uses the 'cover' template (art only, no flowables); switch to 'body' next.
S.append(NextPageTemplate("body"))
S.append(PageBreak())

# ===== INTRO / TOC =====
S.append(Paragraph("Welcome to Piotrack", H1))
S.append(HRFlowable(width="100%", thickness=1, color=LINE, spaceBefore=4, spaceAfter=8))
P("Piotrack is one place to grow your managed-IT (MSP) business. Instead of juggling a website tool, "
  "an email tool, a spreadsheet of leads, an SEO tracker and an ad dashboard, you run all of it here - "
  "and Piotrack connects every activity back to the revenue it produced.", LEAD)
P("This guide walks through the whole app from A to Z in plain language. Each section tells you what a "
  "feature is for, how to use it step by step, and shows a worked example using a sample MSP called "
  "<b>Northwind IT Services</b> in Philadelphia.")
SP(4)
P("What is inside", H2)
toc = [
    "1.  What you need to get started",
    "2.  Signing in and finding your way around",
    "3.  The Dashboard - your growth at a glance",
    "4.  CRM - contacts, companies, leads and deals",
    "5.  Marketing - forms, pages, email and automation",
    "6.  SEO - get found on Google and in AI answers",
    "7.  Advertising - paid campaigns and ROAS",
    "8.  Content, Social and Reputation",
    "9.  Sales - scoring, intent, alerts and booking",
    "10. Analytics and Attribution - what actually made money",
    "11. AI assistant - draft, qualify and research",
    "12. Delivery and the Client Portal",
    "13. Full example - one lead from click to cash",
    "14. Handy tips and FAQ",
]
for t in toc:
    S.append(Paragraph(t, TOCITEM))
S.append(PageBreak())

# ===== 1. WHAT YOU NEED =====
S.append(section(1, "What you need to get started"))
P("To use Piotrack day to day you only need three things:")
S.append(bullets([
    "A modern web browser (Chrome, Edge, Firefox or Safari).",
    "The web address of your Piotrack app and a user account (your administrator invites you by email).",
    "Nothing to install - Piotrack runs entirely in the browser.",
]))
P("Some features talk to outside services. They work in a safe <b>demo mode</b> out of the box so you can "
  "explore, and switch to live once your administrator connects an account:")
S.append(bullets([
    "<b>Email campaigns</b> - connect an email sending provider to send for real.",
    "<b>Advertising</b> - connect your Google / Microsoft Ads accounts to pull in spend and results.",
    "<b>SEO and AI visibility</b> - connect a data provider for live rankings.",
    "<b>AI assistant</b> - connect an AI provider; until then it returns clearly-labelled sample text.",
    "<b>Billing</b> - connect a payment provider to charge customers.",
]))
S.append(note("Anywhere you see a red 'demo / fixture' banner, the numbers are sample data, not live. "
              "It is safe to click around - nothing is sent to a real customer."))

# ===== 2. SIGN IN / NAV =====
S.append(section(2, "Signing in and finding your way around"))
P("Signing in", H2)
S.append(steps([
    "Open your Piotrack web address. You land on the welcome page.",
    "Click <b>Log in</b>, enter your email and password, and you arrive at the Dashboard.",
]))
P("The layout", H2)
S.append(bullets([
    "<b>Left sidebar</b> - every area of the app, grouped: Dashboard, CRM, Marketing, SEO, Advertising, "
    "Content, Website, Sales, Analytics, AI, Delivery and the Client Portal. Click a group to expand it.",
    "<b>Top bar</b> - the collapse button, a <b>Search</b> box (press Ctrl+K / Cmd+K to jump anywhere), "
    "and notifications.",
    "<b>Organization switcher</b> (top-left) - if you manage more than one company in Piotrack, switch "
    "between them here. Everything you see is scoped to the selected organization.",
    "<b>Your account</b> (bottom-left) - profile, settings and the light / dark theme toggle.",
]))
S.append(example("Jumping to a page fast", [
    "Press <b>Ctrl+K</b>, type <font face='Courier'>deals</font>, and press Enter to open your pipeline "
    "without hunting through the menu.",
]))

# ===== 3. DASHBOARD =====
S.append(section(3, "The Dashboard - your growth at a glance"))
P("The Dashboard is your command centre. The cards across the top are your key numbers (KPIs):")
S.append(bullets([
    "<b>New Leads</b> - people who showed interest.",
    "<b>SQLs</b> - sales-qualified leads worth a real conversation.",
    "<b>Meetings</b> - booked calls / assessments.",
    "<b>Open Opportunities</b> - live deals in your pipeline.",
    "<b>Qualified Pipeline</b> - total value of those open deals.",
    "<b>Customers Won</b> - deals closed successfully.",
    "<b>New MRR / ARR</b> - monthly and yearly recurring revenue you have won.",
]))
P("Below the cards, <b>Lead Sources</b> shows where your leads come from (Google, referral, ads, and so "
  "on) as ranked bars, so you can see at a glance which channels are pulling their weight.")
S.append(note("The Dashboard opens with the sidebar collapsed so your numbers get the full width. "
              "Click the collapse button (top-left) any time to bring the menu back."))

# ===== 4. CRM =====
S.append(section(4, "CRM - contacts, companies, leads and deals"))
P("The CRM is your customer database and sales pipeline. It has four lists, all reachable under "
  "<b>CRM</b> in the sidebar.")
P("Contacts and Companies", H2)
P("<b>Contacts</b> are people; <b>Companies</b> are the organisations they work for. Link a contact to a "
  "company to keep everything tidy.")
S.append(steps([
    "Go to <b>CRM -> Contacts</b> and click <b>New contact</b>.",
    "Fill in first name, last name, email, title and phone, then click <b>Create</b>.",
    "Click a contact's name to open their profile - details on the left, full activity history on the right.",
    "Use the <b>Search</b> box to find anyone; use <b>Export CSV</b> to download the list.",
]))
S.append(example("Adding a prospect", [
    "New contact: <b>Michael Reed</b>, IT Director, <font face='Courier'>michael@precisionmfg.test</font>, "
    "linked to the company <b>Precision Manufacturing</b>. His profile now tracks every email, call and "
    "deal tied to him.",
]))
P("Leads and lead scoring", H2)
P("<b>Leads</b> are new, unqualified prospects (for example, a form submission). Piotrack scores each "
  "lead automatically using your rules, so hot leads rise to the top.")
S.append(steps([
    "Open <b>CRM -> Leads</b>. Each row shows the lead, their company, source and status.",
    "Filter by status (new, qualified, converted) using the dropdown.",
    "When a lead is ready, click <b>Convert</b> - Piotrack creates a Contact (and Company) and can open a "
    "Deal in one step.",
]))
S.append(example("Converting a hot lead", [
    "Michael submits your 'Free IT Assessment' form and arrives as a <b>Lead</b>. Scoring rules add points: "
    "'Decision-maker title' (+25) and 'Reached MQL' (+20). At a score of 65 he is clearly hot.",
    "You click <b>Convert</b>, tick <b>Open a deal</b>, enter a value of <b>$4,500</b>, and Piotrack turns "
    "him into a Contact plus a live Deal.",
]))
P("Deals - your pipeline", H2)
P("<b>Deals</b> are opportunities to win. They live on a drag-friendly <b>kanban board</b>, one column per "
  "stage.")
S.append(steps([
    "Open <b>CRM -> Deals</b>. Each column is a stage; the header shows how many deals and their total value.",
    "To move a deal forward, use the stage dropdown on its card (a coloured dot marks won / lost / active).",
    "Click a deal to see its value, MRR, ARR, contact, company, source and expected close date.",
    "Mark a deal <b>Won</b> when it closes - that is what feeds your revenue numbers.",
]))

# ===== 5. MARKETING =====
S.append(section(5, "Marketing - forms, pages, email and automation"))
P("The Marketing area turns visitors into leads and nurtures them until they are ready to buy. Under "
  "<b>Marketing</b> you will find:")
S.append(bullets([
    "<b>Forms</b> - build a form (name, email, message) to capture leads on your site.",
    "<b>Landing Pages</b> - simple campaign pages that host a form.",
    "<b>Lists</b> - groups of contacts you can email.",
    "<b>Campaigns</b> - one-off emails to a list.",
    "<b>Automation</b> - multi-step 'nurture' sequences that send automatically based on behaviour.",
    "<b>Funnels</b> - see how many people move from visit to lead to customer.",
]))
S.append(steps([
    "Create a <b>Form</b> called 'Free IT Assessment'.",
    "Create a <b>Landing Page</b> and place the form on it.",
    "Share the page's link (in an ad, email or your website). Every submission becomes a <b>Lead</b> "
    "automatically, tagged with where it came from.",
    "Build an <b>Automation</b> so new leads get a friendly intro email a day later, then a case study "
    "three days after that.",
]))
S.append(note("Every email respects unsubscribes and suppression automatically, so you stay compliant. "
              "In demo mode, sends are simulated - connect an email provider to send for real."))

# ===== 6. SEO =====
S.append(section(6, "SEO - get found on Google and in AI answers"))
P("SEO helps the right people find you when they search. Under <b>SEO</b>:")
S.append(bullets([
    "<b>Keywords</b> - track the search terms you want to rank for and watch your position over time.",
    "<b>Audit</b> - a health check of your website with fixes to make.",
    "<b>Local</b> - your visibility in each city / branch you serve.",
    "<b>AI Visibility</b> - whether ChatGPT, Gemini and Perplexity mention you when someone asks for the "
    "best MSP in your area.",
    "<b>Schema</b> - structured data that helps search engines understand your pages.",
]))
S.append(example("Tracking a keyword", [
    "Northwind tracks <font face='Courier'>managed IT services Philadelphia</font>. The Keywords page shows "
    "its current position and trend, so they can see their SEO work paying off week by week.",
]))

# ===== 7. ADVERTISING =====
S.append(section(7, "Advertising - paid campaigns and ROAS"))
P("The Advertising area brings your paid campaigns into Piotrack so you can see what you spend and what "
  "you get back.")
S.append(bullets([
    "<b>Campaigns</b> - impressions, clicks, spend, conversions and revenue per campaign.",
    "<b>Retargeting</b> - re-reach people who visited but did not convert.",
    "<b>ROAS</b> (return on ad spend) - revenue divided by spend. A ROAS of 4.57x means every $1 spent "
    "returned $4.57.",
]))
S.append(note("Connect your Google / Microsoft Ads account to pull real numbers; until then you will see "
              "clearly-labelled sample data."))

# ===== 8. CONTENT =====
S.append(section(8, "Content, Social and Reputation"))
P("Publish helpful content, keep social active, and stay on top of reviews - all under <b>Content</b>.")
S.append(bullets([
    "<b>Content</b> - plan and draft blog posts and guides.",
    "<b>Social</b> - schedule and track social posts.",
    "<b>Reputation</b> - collect and respond to customer reviews in one place.",
    "<b>Outreach</b> - coordinate link-building and partner outreach.",
]))

# ===== 9. SALES =====
S.append(section(9, "Sales - scoring, intent, alerts and booking"))
P("The Sales area helps your reps focus on the right prospects at the right moment.")
S.append(bullets([
    "<b>Scoring</b> - the rules that grade every lead (edit these to match your ideal customer).",
    "<b>Intent</b> - signals that a prospect is actively looking to buy.",
    "<b>Alerts</b> - a notification the instant a lead turns hot, so a rep can reach out while they are warm.",
    "<b>Booking</b> - a meeting scheduler prospects use to book a call with you.",
    "<b>Enablement</b> - your sales playbooks and materials.",
    "<b>Accounts</b> - your key target accounts.",
]))
S.append(example("A scoring rule", [
    "Rule: if a contact's title <b>contains 'director'</b>, add <b>25 points</b>. Decision-makers now float "
    "to the top of your reps' lists automatically.",
]))

# ===== 10. ANALYTICS =====
S.append(section(10, "Analytics and Attribution - what actually made money"))
P("This is where Piotrack earns its keep. It answers the question every MSP owner asks: "
  "<b>which activities actually bring in paying customers?</b>")
S.append(bullets([
    "<b>Attribution</b> - traces a won deal back through the meeting, the lead, the campaign and even the "
    "keyword that started it.",
    "<b>Growth Score</b> - one 0-100 number grading your whole growth engine, with the weakest area to fix "
    "next.",
    "<b>Benchmarks</b> - how you compare to typical MSPs.",
    "<b>Omnichannel</b> - all your channels side by side.",
    "<b>Calls</b> - call tracking and outcomes.",
    "<b>Experiments</b> - A/B tests to see what works better.",
    "<b>Competitors</b> - keep an eye on rivals.",
]))
S.append(example("Attribution in action", [
    "The $4,500/mo deal with Precision Manufacturing is marked <b>Won</b>. Attribution shows the whole "
    "path: <font face='Courier'>Google search -> Free IT Assessment form -> Lead -> meeting -> Deal -> Won</font>. "
    "Now you know that one Google keyword produced real recurring revenue - so you invest more there.",
]))

# ===== 11. AI =====
S.append(section(11, "AI assistant - draft, qualify and research"))
P("The AI area is a sales assistant. Under <b>AI</b>:")
S.append(bullets([
    "<b>Agent</b> - pick a contact and a task (qualify, research, draft an email) and get a suggestion; or "
    "paste a prospect's objection and get a suggested response.",
    "<b>Approvals</b> - the AI never changes your data silently. Proposed CRM updates wait here for you to "
    "approve first.",
    "<b>Conversations / Prompts / Visibility</b> - history, reusable prompts, and how AI engines see your brand.",
]))
S.append(steps([
    "Open <b>AI -> Agent</b>.",
    "Under 'Run a task on a contact', pick a contact, choose <b>Qualify</b>, and click <b>Run task</b>.",
    "To handle a pushback, type it under 'Handle an objection' and click <b>Suggest a response</b>.",
    "Any change the AI proposes appears under <b>Approvals</b> - review, then approve or reject.",
]))
S.append(note("By default the AI runs in fixture mode and returns clearly-labelled sample text - do not send "
              "it to a customer. Ask your administrator to connect a live AI provider for real output."))

# ===== 12. DELIVERY / PORTAL =====
S.append(section(12, "Delivery and the Client Portal"))
P("Once you win a customer, the Delivery area and Client Portal keep them happy.")
S.append(bullets([
    "<b>Projects</b> - track onboarding and delivery work.",
    "<b>Support</b> - handle customer tickets.",
    "<b>Strategy / Brand / Performance</b> - your plan, brand assets and how you are tracking against goals.",
    "<b>Client Portal</b> - a clean, shareable space where your customers can see their own progress.",
]))

# ===== 13. FULL EXAMPLE =====
S.append(section(13, "Full example - one lead from click to cash"))
P("Here is how the whole app works together, following one real journey end to end at Northwind IT "
  "Services:")
S.append(steps([
    "<b>Get found.</b> Michael searches Google for 'managed IT services Philadelphia' and finds Northwind "
    "(SEO).",
    "<b>Capture.</b> He clicks a landing page and submits the 'Free IT Assessment' form (Marketing). He "
    "arrives in Piotrack as a <b>Lead</b>, tagged source = Google.",
    "<b>Qualify.</b> Scoring rules push him to 65 - hot. An <b>Alert</b> fires to a sales rep (Sales).",
    "<b>Meet.</b> The rep uses <b>AI -> Agent</b> to draft a tailored intro email, and Michael books a call "
    "via the Booking page.",
    "<b>Open a deal.</b> The rep <b>Converts</b> the lead into a Contact plus a <b>$4,500/mo Deal</b> (CRM).",
    "<b>Progress and win.</b> The deal moves across the pipeline and is marked <b>Won</b> (CRM).",
    "<b>Prove it.</b> The Dashboard MRR/ARR go up, and <b>Attribution</b> credits the win to that Google "
    "keyword (Analytics) - so Northwind confidently invests more in what works.",
]))
S.append(example("The payoff", [
    "One number ties it together: that journey turned a single search into <b>$54,000 of tracked ARR</b>, "
    "and Piotrack shows exactly which activity earned it.",
]))

# ===== 14. TIPS =====
S.append(section(14, "Handy tips and FAQ"))
S.append(bullets([
    "<b>Search anything</b> - press Ctrl+K (Cmd+K on Mac).",
    "<b>Dark mode</b> - use the sun / moon toggle by your name (bottom-left).",
    "<b>Multiple companies</b> - switch with the organisation picker (top-left); data never mixes between them.",
    "<b>Export</b> - most lists have an <b>Export CSV</b> button.",
    "<b>Permissions</b> - if you cannot see a menu, your role does not include it - ask your administrator.",
    "<b>'Session expired'</b> - if you have been idle a long time, just sign in again; your work is saved.",
    "<b>Demo banners</b> - red 'fixture' banners mean sample data; connect the matching provider to go live.",
]))
SP(6)
S.append(HRFlowable(width="100%", thickness=1, color=LINE, spaceBefore=4, spaceAfter=8))
P("That is Piotrack, A to Z. Start on the Dashboard, add a few contacts, capture a lead with a form, and "
  "watch the pipeline and attribution light up. Welcome aboard.", LEAD)

doc.build(S)
print("wrote", OUT)
