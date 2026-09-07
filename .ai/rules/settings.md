---
paths:
  - resources/views/pages/settings/⚡team.blade.php
---

# Settings

## Confirm team invites in a modal; show desk coverage
Invite form submits promptInvite, which validates then opens confirm-team-invite. inviteTeammate is the send action. Role cards and the people table use OperatorUserRole::deskScope(), not a one-line blurb.

## Invite accordion starts closed
Invite someone is a closed accordion (Alpine inviteOpen false) so the people table leads. Open it when invite fields have errors. Remove is a rose bordered button, not ghost.
