<?php

test('deploy workflow allows long-running remote builds', function () {
    $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');

    expect($workflow)->not->toBeFalse()
        ->and($workflow)->toContain('command_timeout: 120m');
});
