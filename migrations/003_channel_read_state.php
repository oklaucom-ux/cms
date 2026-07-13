<?php
return [
    "CREATE TABLE IF NOT EXISTS channel_read_state (
        user_id VARCHAR(255) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        last_read_msg_id INTEGER DEFAULT 0,
        PRIMARY KEY (user_id, channel_name)
    )"
];
