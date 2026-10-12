# Contributing to WP Server Toolkit

Thank you for your interest in contributing to **WP Server Toolkit**! Contributions that improve server visibility, reliability, security, usability, documentation, and testing are welcome.

WP Server Toolkit brings Linux server and service monitoring into WordPress, including system metrics, service status, WordPress health, SSL tracking, alerts, and multi-server support. Please keep contributions aligned with the project's goals and security model.

## Table of Contents

- [Ways to Contribute](#ways-to-contribute)
- [Before You Start](#before-you-start)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)
- [Development Setup](#development-setup)
- [Code Guidelines](#code-guidelines)
- [Security Requirements](#security-requirements)
- [Testing](#testing)
- [Pull Requests](#pull-requests)
- [Reporting Security Issues](#reporting-security-issues)
- [Community Guidelines](#community-guidelines)
- [License](#license)

## Ways to Contribute

You can help by:

- Fixing bugs and improving existing features.
- Improving Linux server metrics and service monitoring.
- Enhancing WordPress dashboard usability and accessibility.
- Improving SSL certificate tracking, alerts, and health checks.
- Improving multi-server registration and status reporting.
- Strengthening authentication, authorization, validation, and logging.
- Adding tests and improving troubleshooting documentation.
- Testing compatibility with supported WordPress, PHP, and Linux environments.

For significant changes, open an issue first to discuss the proposed design and scope.

## Before You Start

- Check the [existing issues](https://github.com/edatastyle/wordpress-server-toolkit/issues) and pull requests for related work.
- Review the repository's `README.md`, `LICENSE`, and existing code conventions.
- Keep changes focused and avoid combining unrelated fixes.
- Do not include credentials, private keys, bearer tokens, production logs, or personal data in commits or issue reports.

## Reporting Bugs

When opening a bug report, include:

- A clear description and steps to reproduce the issue.
- Expected behavior and actual behavior.
- WordPress and PHP versions.
- Linux distribution and version, where relevant.
- Web server and relevant service versions, if applicable.
- Sanitized error messages or logs.
- Any troubleshooting steps already attempted.

Remove or redact passwords, tokens, public IPs where sensitive, hostnames, and other identifying infrastructure details before sharing diagnostic information publicly.

## Suggesting Features

Feature suggestions are welcome. Please describe:

1. The problem or user need.
2. The proposed behavior and expected benefit.
3. Any alternative approaches considered.
4. Compatibility, performance, privacy, and security implications.

Ideas and discussion can be raised through [GitHub issues](https://github.com/edatastyle/wordpress-server-toolkit/issues). For larger changes, wait for feedback before investing in a substantial implementation.

## Development Setup

### Prerequisites

The exact requirements depend on the area being changed. Check the repository's documentation and dependency files. Development may require:

- WordPress and PHP in versions supported by the project.
- A local WordPress development environment.
- Git.
- A Linux test server or virtual machine for agent and system-monitoring changes.
- Any JavaScript, CSS, PHP, or test tooling documented in the repository.

Use a disposable or staging environment for tests that interact with server services or system configuration. Do not experiment on production servers.

### Clone the Repository

```bash
git clone https://github.com/edatastyle/wordpress-server-toolkit.git
cd wordpress-server-toolkit
```

Create a branch for your change:

```bash
git switch -c fix/short-description
```

Examples:

```bash
git switch -c fix/service-status-check
 git switch -c feature/ssl-alert-improvements
 git switch -c docs/installation-guide
```

Use the repository's documented commands to install dependencies, run the development environment, and execute tests. Avoid assuming that commands or dependencies exist unless they are documented by the project.

## Code Guidelines

### WordPress and PHP

- Follow WordPress coding standards and the conventions already used in the codebase.
- Sanitize and validate input, and escape output for its context.
- Use WordPress APIs where appropriate.
- Check capabilities and authorization for privileged actions.
- Protect state-changing requests with appropriate nonce verification and authorization checks.
- Use prepared database queries when handling variable data in SQL.
- Handle failures clearly without exposing secrets or sensitive server details.
- Preserve compatibility with the project's declared minimum supported versions.

### Server Agent and System Integration

- Keep monitoring operations narrow, predictable, and documented.
- Validate data received from WordPress before processing it on the server.
- Return structured, bounded responses and handle unavailable services gracefully.
- Avoid unnecessary privileges and minimize the scope of any privileged operation.
- Do not silently alter firewall rules, services, users, permissions, or server configuration.
- Avoid logging credentials, bearer tokens, or sensitive system information.

### JavaScript, CSS, and User Interface

- Follow existing formatting and component patterns.
- Keep the interface responsive, accessible, and consistent with WordPress conventions.
- Validate data before displaying it and handle loading, empty, and error states.
- Avoid introducing large dependencies for small features.

## Security Requirements

Security is a core requirement of WP Server Toolkit. Every contribution must preserve the project's security model.

- **Do not introduce free-form remote command execution.** Server-side operations must remain limited to explicitly defined, validated actions.
- Treat registration credentials, bearer tokens, and other authentication material as secrets.
- Never hardcode credentials or place tokens in logs, URLs, screenshots, or example configuration.
- Apply least privilege and verify authorization for every privileged operation.
- Validate and constrain data crossing the WordPress-to-server boundary.
- Use authenticated communication and follow the project's existing registration and request-validation design.
- Avoid exposing server details to users who are not authorized to view them.
- Do not disable security controls or weaken authentication to make tests pass.
- Document security-sensitive behavior and provide safe failure handling.

Changes that affect authentication, registration, token handling, agent communication, or privileged operations should explain the threat model and testing performed in the pull request.

## Testing

Run the checks documented by the repository and test the behavior affected by your change. Depending on the contribution, verify:

- Plugin activation and deactivation.
- Dashboard behavior for authorized and unauthorized users.
- Server registration and authentication flows.
- Metrics and service status when services are available or unavailable.
- SSL checks, alerts, and error conditions.
- Handling of invalid input, expired credentials, and failed requests.
- Compatibility with supported WordPress and PHP versions.
- That no secrets appear in logs, API responses, or user-facing errors.

For PHP files, a basic syntax check can be run with:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -r -n1 php -l
```

This is only a syntax check, not a substitute for functional or security testing. Run any project-specific linting and test commands documented in the repository. If you cannot run a test, state that clearly in your pull request.

## Pull Requests

Before submitting a pull request:

1. Review your diff and remove unrelated changes.
2. Check that no secrets, credentials, or sensitive logs are included.
3. Run relevant linting and tests.
4. Update documentation when behavior or configuration changes.
5. Use a clear commit message.
6. Open a pull request against the repository's target branch.

Example:

```bash
git add .
git commit -m "Improve service status handling"
git push -u origin fix/service-status-check
```

Include the following in your pull request:

- **Summary:** What changed and why.
- **Related issue:** Link to the issue, if applicable.
- **Implementation:** Important design details.
- **Testing:** Commands run and their results.
- **Security impact:** Changes to authentication, tokens, server communication, or privileged behavior.
- **Compatibility:** Relevant WordPress, PHP, and Linux versions tested.

Keep pull requests focused and respond constructively to review feedback. Maintainers may request changes before merging.

## Reporting Security Issues

Do not publish exploitable vulnerability details in a public issue. Use GitHub's private vulnerability reporting feature for the repository, if available, or contact the maintainer through an established private channel.

Include the affected component, impact, reproduction steps, affected versions, and suggested mitigation where possible. Do not include live credentials, bearer tokens, or sensitive production data.

## Community Guidelines

Please be respectful and constructive:

- Welcome contributors with different experience levels.
- Focus feedback on code and technical decisions, not individuals.
- Explain disagreements professionally.
- Respect privacy and confidentiality.
- Avoid harassment, personal attacks, and spam.

## License

By submitting a contribution, you agree that it may be distributed under the license governing this repository. Review the repository's `LICENSE` file before contributing. If the licensing terms are unclear, contact the maintainer before submitting work intended for distribution.

Thank you for helping improve WP Server Toolkit!

**Repository:** https://github.com/edatastyle/wordpress-server-toolkit
