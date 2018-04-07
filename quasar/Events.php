<?php

//--------------------------------------------------------------------------
// The SocketIO Events for one Namespace / Application
//--------------------------------------------------------------------------


// Triggered when the client sends a subscribe event.
$socket->on('subscribe', function ($channel, $authKey = null, $data = null) use ($socket, $senderIo, $secretKey)
{
    $channel = (string) $channel;

    $errorEvent = $channel .'#presence:subscription_error';

    //
    $socketId = $socket->id;

    if (preg_match('#^(?:(private|presence)-)?([-a-zA-Z0-9_=@,.;]+)$#', $channel, $matches) !== 1) {
        $senderIo->to($socketId)->emit($errorEvent, 400);

        return;
    }

    $type = ! empty($matches[1]) ? $matches[1] : 'public';

    if ($type == 'public') {
        $socket->join($channel);

        return;
    } else if (empty($authKey)) {
        $senderIo->to($socketId)->emit($errorEvent, 400);

        return;
    }

    if ($type == 'private') {
        $hash = hash_hmac('sha256', $socketId .':' .$channel, $secretKey, false);
    }

    // A presence channel must have a non empty data argument.
    else if (empty($data)) {
        $senderIo->to($socketId)->emit($errorEvent, 400);

        return;
    } else /* presence channel */ {
        $hash = hash_hmac('sha256', $socketId .':' .$channel .':' .$data, $secretKey, false);
    }

    if ($hash !== $authKey) {
        $senderIo->to($socketId)->emit($errorEvent, 403);

        return;
    }

    $socket->join($channel);

    if ($type == 'private') {
        return;
    }

    // A presence channel additionally needs to store the subscribed member's information.
    else if (! isset($senderIo->presence[$channel])) {
        $senderIo->presence[$channel] = array();
    }

    $members =& $senderIo->presence[$channel];

    // Decode the member information.
    $payload = json_decode($data, true);

    $member = array(
        'id'   => $payload['userId'],
        'info' => $payload['userInfo']
    );

    // Determine if the user is already a member of this channel.
    $userId = $member['id'];

    $alreadyMember = ! empty(array_filter($members, function ($member) use ($userId)
    {
        return $member['id'] == $userId;
    }));

    $members[$socketId] = $member;

    // Emit the events associated with the channel subscription.
    $items = array();

    foreach (array_values($members) as $item) {
        if (! array_key_exists($key = $item['id'], $items)) {
            $items[$key] = $item['info'];
        }
    }

    $data = array(
        'me'      => $member,
        'members' => array_values($items),
    );

    $senderIo->to($socketId)->emit($channel .'#presence:subscribed', $data);

    if (! $alreadyMember) {
        $socket->to($channel)->emit($channel .'#presence:joining', $member);
    }
});

// Triggered when the client sends a unsubscribe event.
$socket->on('unsubscribe', function ($channel) use ($socket, $senderIo)
{
    $socketId = $socket->id;

    $channel = (string) $channel;

    if ((strpos($channel, 'presence-') === 0) && isset($senderIo->presence[$channel])) {
        $members =& $senderIo->presence[$channel];

        if (array_key_exists($socketId, $members)) {
            $member = array_pull($members, $socketId);

            // Determine if the user is still a member of this channel.
            $userId = $member['id'];

            $isMember = ! empty(array_filter($members, function ($member) use ($userId)
            {
                return $member['id'] == $userId;
            }));

            if (! $isMember) {
                $socket->to($channel)->emit($channel .'#presence:leaving', $member);
            }
        }

        if (empty($senderIo->presence[$channel])) {
            unset($senderIo->presence[$channel]);
        }
    }

    $socket->leave($channel);
});

// Triggered when the client sends a message event.
$socket->on('channel:event', function ($channel, $event, $data) use ($socket)
{
    if (preg_match('#^(private|presence)-(.*)#', $channel) !== 1) {
        // The requested channel is not a private one.
        return;
    }

    // If it is a client event and socket joined the channel, we will emit this event.
    if ((preg_match('#^client-(.*)$#', $event) === 1) && isset($socket->rooms[$channel])) {
        $eventName = $channel .'#' .$event;

        $socket->to($channel)->emit($eventName, $data);
    }
});

// When the client is disconnected is triggered (usually caused by closing the web page or refresh)
$socket->on('disconnect', function () use ($socket, $senderIo)
{
    $socketId = $socket->id;

    foreach ($senderIo->presence as $channel => &$members) {
        if (! array_key_exists($socketId, $members)) {
            continue;
        }

        $member = array_pull($members, $socketId);

        // Determine if the user is still a member of this channel.
        $userId = $member['id'];

        $isMember = ! empty(array_filter($members, function ($member) use ($userId)
        {
            return $member['id'] == $userId;
        }));

        if (! $isMember) {
            $socket->to($channel)->emit('presence:leaving', $channel, $member);
        }

        if (empty($senderIo->presence[$channel])) {
            unset($senderIo->presence[$channel]);
        }
    }
});
