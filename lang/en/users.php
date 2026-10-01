<?php

return [
    'title' => 'Users',
    'help' => 'The people who can sign in. Every user can do everything: there are no limited accounts.',
    'you' => 'You',
    'add' => 'Add a user',
    'full' => 'There are already :max active users, the most allowed. Deactivate one before adding another.',
    'change_my_password' => 'Change my password',

    'labels' => [
        'name' => 'Name',
        'email' => 'Email',
        'status' => 'Status',
        'added' => 'Added',
        'password_chosen' => 'Password chosen',
        'last_signed_in' => 'Last signed in',
        'deactivated' => 'Deactivated',
    ],
    'not_yet' => 'Not yet',
    'never' => 'Never',

    'status' => [
        'active' => 'Active',
        'waiting' => 'Waiting for a password',
        'deactivated' => 'Deactivated',
    ],

    'added_on' => 'Added :date',
    'last_signed_in' => 'Last signed in :time',
    'never_signed_in' => 'Never signed in',

    'create' => [
        'title' => 'Add a user',
        'help' => 'They will get a link that works once, for an hour, to choose their own password. You never see or choose it.',
        'button' => 'Add user and make their link',
    ],
    'created' => 'Added :name. Give them the link below.',

    'edit' => 'Edit details',
    'edit_title' => 'Edit :name',
    'email_help' => 'They sign in with this address. Nothing is sent to it.',
    'email_taken' => 'Another user already signs in with that email address. Use a different one.',
    'updated' => 'Changes saved.',

    'link' => [
        'heading' => 'Their password link',
        'works_until' => 'Works once, until :time.',
        'how' => 'Give it to :name in person, or send it to them privately. Whoever opens it can choose the password, so never post it in a group.',
        'scan' => 'They can scan this code with their phone, or open the link below.',
        'shown_once' => 'This link is shown only now. If it is lost, make a new one.',
        'copy' => 'The link',
    ],

    'waiting' => ':name has not chosen a password yet, so cannot sign in.',
    'waiting_help' => 'If their link was lost or has run out, make a new one. The old one stops working.',
    'new_link' => 'Make a new link',
    'new_link_done' => 'A new link for :name is ready. The old one no longer works.',

    'reset' => 'Reset password',
    'reset_help' => 'Their current password stops working at once and they are signed out everywhere. You get a link with which they choose a new one.',
    'reset_confirm' => 'Reset the password for :name? Their current password stops working now.',
    'reset_done' => 'The password for :name was reset. Give them the link below.',

    'deactivate' => 'Deactivate',
    'deactivate_help' => 'They can no longer sign in and are signed out everywhere. Everything they did stays in the history.',
    'deactivate_confirm' => 'Deactivate :name? They are signed out at once and can no longer sign in.',
    'deactivated' => ':name can no longer sign in.',

    'reactivate' => 'Reactivate',
    'reactivate_help' => 'They can sign in again with the password they had. If anyone else may know it, reset it as well.',
    'reactivated' => ':name can sign in again.',

    // Shown when a request asks for something the guardrails refuse. The
    // buttons are hidden in those cases, so these mostly answer a hand-made
    // request or two admins acting at the same moment.
    'rules' => [
        'you_cannot_sign_in' => 'Your account can no longer sign in.',
        'reset_yourself' => 'To change your own password, use Change my password.',
        'reset_deactivated' => 'Reactivate this user before resetting their password.',
        'deactivate_yourself' => 'You cannot deactivate your own account. Another user has to do it.',
        'already_deactivated' => 'That user is already deactivated.',
        'already_active' => 'That user is already active.',
        'full' => 'There are already :max active users, the most allowed. Deactivate one first.',
    ],

    'confirm' => [
        'title' => 'Enter your password again',
        'help' => 'Managing users needs your password once more. You will not be asked again for :minutes minutes.',
        'button' => 'Continue',
    ],

    'password' => [
        'title' => 'Change my password',
        'help' => 'Any other phone or computer you are signed in on is signed out. This one stays signed in.',
        'current' => 'Your current password',
        'new' => 'New password',
        'new_again' => 'New password again',
        'wrong' => 'That is not your password. Check it and try again.',
        // Lower case: it lands mid-sentence in "The new password field must…".
        'attribute' => 'new password',
        'mismatch' => 'The two new passwords are not the same. Type it exactly the same way twice.',
        'unchanged' => 'That is the password you have now. Choose a different one.',
        'changed' => 'Your password is changed. Any other device you were signed in on has been signed out.',
    ],

    'setup' => [
        'title' => 'Choose your password',
        'for' => 'For :name, who signs in as :email',
        'button' => 'Save my password',
        'done' => 'Your password is saved. Sign in with it now.',
        'unavailable_heading' => 'This link cannot be used',
        'unavailable_help' => 'It has already been used, has run out, or was replaced by a newer one. Ask the school office for a new link.',
        'busy_heading' => 'Too many tries',
        'busy_help' => 'Wait a minute, then open the link again.',
    ],
];
