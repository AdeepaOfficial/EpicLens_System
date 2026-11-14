# EpicLens System

EpicLens System is a web application built using **HTML5, CSS3, JavaScript, PHP, and MySQL**.

## Architecture & Tech Stack
- **Frontend**: HTML5, CSS3, Modern JavaScript (ES6+)
- **Backend**: PHP (Modular Object-Oriented Architecture & REST APIs)
- **Database**: MySQL (Relational Schema & Optimized Queries)
- **Quality Assurance**: Automated & Cross-browser Testing, Debug Suites
- **DevOps**: Git Flow Branching Strategy, CI/CD-ready structure

## Project Structure
```text
EpicLens_System/
├── config/             # Database and environment configurations
├── database/           # SQL schema definitions and migrations
├── docs/               # Technical specs and branching documentation
├── public/             # Web server document root (HTML/CSS/JS entry point)
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── index.php
├── src/                # Backend application code (Controllers, Models)
├── .gitignore          # Git exclusion rules
└── README.md           # Project documentation
```

## Branching Model
Development strictly follows the Git Flow branching model outlined in [docs/BRANCHING_STRATEGY.md](docs/BRANCHING_STRATEGY.md).
- `main` — Production release branch
- `develop` — Active development and integration branch
- `feature/*` — Isolated feature branches
- `bugfix/*` — Bug and issue resolution branches

## Team Roles
- **CEO**: Project supervision and coordination
- **UX/UI Designer**: User experience, wireframes, and design system
- **Frontend Developer**: HTML, CSS, JavaScript interface implementation
- **Backend Developer**: PHP application logic, session management, and APIs
- **Database Engineer**: MySQL relational schema and queries
- **QA Engineer**: Test execution, verification, and bug tracking
- **DevOps Engineer**: Version control, branch policies, and GitHub deployment
