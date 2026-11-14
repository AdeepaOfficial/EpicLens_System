# EpicLens System - Campus Event Equipment Tracking Platform

A secure, modern, responsive web application for managing campus event equipment lending and return lifecycles.

Built with **HTML5, Modern Vanilla CSS3, ES6 JavaScript, PHP 8+, and MySQL (via PDO)**.

---

## 🚀 Key Features & Highlights

- **4-Stage Real-World Lending Workflow**:
  1. *Check-in from Owner* (`Not Received Yet` &rarr; `Received`)
  2. *Assign Event Crew Member* (Equipment marked In-Use)
  3. *Crew Hand-In* (Returned to team custody, condition logged)
  4. *Final Return to Student Owner* (`Received` &rarr; `Returned`)
- **Strict 3 Event Statuses**: `Not Received Yet`, `Received`, `Returned`.
- **Permanent Category Item Codes**: Unique sequential prefixes (`CM001`, `TP002`, `LN003`) with zero recycling of deleted IDs.
- **Enterprise Security**:
  - Global CSRF token protection on all state-changing actions.
  - Hardened sessions (`HttpOnly`, `SameSite=Lax`, strict mode).
  - Password hashing with `PASSWORD_DEFAULT` (bcrypt).
  - Zero hardcoded secrets (configured via `.env`).
- **Privacy First**: Zero student or sample data committed to version control.
- **Responsive Modern UI**:
  - Collapsible mobile navigation drawer.
  - Live table search with debounced filtering.
  - Interactive item selection previews.
  - Printable audit vouchers.

---

## 📁 Project Structure

```text
EpicLens_System/
├── config/
│   ├── database.php        # PDO connection, environment loader & code sequences
│   └── .env.example        # Environment configuration template
├── database/
│   ├── schema.sql          # Clean DDL (zero personal/sample data)
│   └── migrate.php         # CLI schema migration runner
├── docs/
│   ├── BRANCHING_STRATEGY.md # Git Flow branch rules
│   └── SYSTEM_ARCHITECTURE.md# Architecture & security comparisons
├── includes/
│   ├── auth.php            # Session guards & role-based permissions
│   ├── csrf.php            # CSRF token generator & validator
│   ├── helpers.php         # Sanitization & UI presenters
│   ├── header.php          # Responsive topbar & layout header
│   ├── sidebar.php         # Role-aware navigation sidebar
│   └── footer.php          # Modals & script inclusions
├── public/
│   ├── assets/
│   │   ├── css/style.css   # Modern CSS design system
│   │   └── js/script.js    # Interactive UI controllers
│   ├── dashboard.php       # Role-adaptive status dashboard
│   ├── receive.php         # Check-in gear from student owners
│   ├── return.php          # 3-step crew and owner returns
│   ├── equipment.php       # Equipment catalog & event reset
│   ├── equipment_add.php   # Register new equipment with code preview
│   ├── equipment_edit.php  # Edit equipment master records
│   ├── equipment_delete.php# Secure POST deletion handler
│   ├── staff.php           # Staff account management
│   ├── transactions.php    # Audit history with search & date filters
│   ├── transaction_view.php# Printable transaction voucher
│   ├── setup.php           # First-time database & admin wizard
│   ├── login.php           # Secure login & onboarding
│   ├── logout.php          # Session termination
│   └── index.php           # Entry point redirector
├── .gitignore              # Enforces exclusion of .env, dumps, private data
└── README.md               # Documentation
```

---

## 🛠️ Quick Start & Setup

1. **Configure Environment**:
   ```bash
   cp config/.env.example .env
   # Edit .env with your MySQL database credentials
   ```

2. **Initialize Database**:
   - **Option A (Browser Wizard)**: Navigate to `public/setup.php` and fill in the initial administrator details.
   - **Option B (CLI)**:
     ```bash
     php database/migrate.php
     ```

3. **Serve Application**:
   Point your web server (Apache/Nginx/PHP built-in) to the project or `public/` directory:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
