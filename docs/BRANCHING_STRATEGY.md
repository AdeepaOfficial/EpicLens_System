# EpicLens System - Branching & Version Control Strategy

This repository enforces a structured Git branching workflow managed by the DevOps Engineer and followed by all development agents.

## 1. Permanent Branches
- **`main`**: Production branch. Contains only tested, reviewed, and stable code. Direct pushes are restricted; updates arrive via merges from `develop` or hotfix branches.
- **`develop`**: Integration branch. Serves as the active development branch where features from the team are merged, integrated, and validated.

## 2. Supporting Branches
- **`feature/<feature-name>`**:
  - Originates from: `develop`
  - Merges into: `develop`
  - Purpose: New user interfaces, endpoints, database models, or features.
  - Examples: `feature/ui-dashboard`, `feature/auth-backend`, `feature/schema-setup`
- **`bugfix/<issue-name>`**:
  - Originates from: `develop`
  - Merges into: `develop`
  - Purpose: Resolving issues, bugs, and edge cases identified during testing.
- **`hotfix/<issue-name>`**:
  - Originates from: `main`
  - Merges into: `main` and `develop`
  - Purpose: Critical production fixes requiring immediate patch releases.

## 3. Commit Convention
- Format: `<type>(<scope>): <short description>`
- Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- All OpenBot agent commits carry the mandatory co-authorship trailer.
