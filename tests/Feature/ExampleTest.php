<?php

test('the application returns a successful response', function () {
    $this->get('/docs')->assertStatus(200);
});
