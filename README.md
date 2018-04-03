# Quasar - a Push Server for Nova Framework

The **Quasar Push Server** is a PHP console application which implements a **WebSocket Server** made in top of **[PHPSocketIO](https://github.com/walkor/phpsocket.io)**, which supports multiple clients and it is compatible with the **[socket.io-client](https://github.com/socketio/socket.io-client)** library.

It have support for **public** and **private** channels. Additionaly, it offers a **WEB** interface which accepts authenticated **HTTP** requests for sending messages to particular channels.

The **Quasar Push Server** being written specially for working with [Nova Framework 4.x](https://github.com/nova-framework/framework/tree/4.0), the client infrastructure is already integrated in the framework.

## Client implementation example
A typical client implementation is as following:
```html
<script src='https://cdnjs.cloudflare.com/ajax/libs/socket.io/2.1.0/socket.io.js'></script>

<script>
function socket_subscribe(socket, channel, type = 'public') {
    if (type === 'public') {
        socket.emit('subscribe', channel);

        return;
    } else if ((type !== 'private') && (type !== 'presence')) {
        console.log('Invalid channel type', type);

        return;
    }

    var channelName = type + '-' + channel;

    $.ajax({
        url: '<?= site_url('broadcasting/auth'); ?>',
        type: 'POST',
        headers: {
            '_token': '<?= csrf_token(); ?>',
            'X-Socket-ID': socket.id
        },
        data: {
            channel_name: channelName,
            socket_id: socket.id
        },
        dataType: 'json',
        timeout : 15000,

        success: function (data) {
            socket.emit('subscribe', channelName, data.auth, data.payload || '');
        },
        error: function (xhr, ajaxOptions, thrownError) {
            socket.disconnect();
        }
    });
};
<script>
   
<?php $config = Config::get('broadcasting.connections.quasar'); ?>

<script>
$(document).ready(function () {
    var host  = '<?= array_get($config, 'host') . ':' . array_get($config, 'socket'); ?>';
    var appId = '<?= array_get($config, 'appId'); ?>';

    var userChannel = 'Modules.Users.Models.User.<?= Auth::id(); ?>';
    var chatChannel = 'chat';

    // The connection server.
    var socket = io.connect(host + '/' + appId);

    // Subscribe after connecting.
    socket.on('connect', function () {
        socket_subscribe(socket, userChannel, 'private');
        socket_subscribe(socket, chatChannel, 'presence');
    });
    
    // Listen to a sample event.
    var event = 'Modules.Broadcast.Events.Sample';

    socket.on(event, function (data) {
        console.log('Received event', event);
        console.log(data);

        $('#content') .append('<p>Received event: <b>' + event + '</b></p>');
    });
});
</script>
```
Compared with the usual SocketIO client code, there is the need to subscribe to channels via an authenticated way, executed by `socket_subscribe()` when the socket is connected to the Quasar instance and receive the event `connect`.

This function interrogate the authentication end-point from the Nova application, then it emit the `subscribe` event to socket. After successfully joing the channel, the listenting to broadcasted events is as usual for an SocketIO client.

In the page example, the code listens to the event `Modules.Broadcast.Events.Sample`, which in the application side represents an Event class `Modules\Broadcast\Events\Sample`, as following:
```php
namespace Modules\Broadcast\Events;

use Nova\Broadcasting\Channel;
use Nova\Broadcasting\PrivateChannel;
use Nova\Broadcasting\PresenceChannel;
use Nova\Broadcasting\ShouldBroadcastInterface;

use Nova\Broadcasting\InteractsWithSocketsTrait;
use Nova\Foundation\Events\DispatchableTrait;
use Nova\Queue\SerializesModelsTrait;

use Nova\Support\Facades\Auth;

use Modules\Users\Models\User;


class Sample implements ShouldBroadcastInterface
{
    use DispatchableTrait, InteractsWithSocketsTrait, SerializesModelsTrait;

    /**
     * @var Modules\Users\Models\User
     */
    public $user;

    /**
     * @var int
     */
    private $userId;


    /**
     * Create a new Event instance.
     *
     * @return void
     */
    public function __construct(User $user, $userId)
    {
        $this->user = $user;

        $this->userId = $userId;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('Modules.Users.Models.User.' .$this->userId);
    }
}
```
You will notice that the Quasar event name replaces the `\` with `.` in the full namespaced class name. Then the class `Modules\Broadcast\Events\Sample` generates the event `Modules.Broadcast.Events.Sample`.

Finally, this **Event** is broadcasted by the following code:
```php
$user = Auth::user();

broadcast(new SampleEvent($user, $user->id));
```
## Requirements

Please note that the **Quasar Push Server** is a PHP console application, which runs in the Linux console.

That's why it cannot be installed in a web-server like Apache or NGINX, neither to be used in a shared hosting or similar infrastructures. Aditionally, it probably will not work in Windows, even for testing purposes.

Of course, those requirements does not apply to any of the Nova applications which use its features, and they can be located in the same server or in different ones. 

## Installation

It is recommended to clone its repository, via an command like
```text
git clone https://github.com/nova-framework/quasar.git
```
There are no requirements for a specific location of the **quasar** folder, because it is an stand-alone application.

After cloning, you should install the required Composer packages, running within folder the command:
```text
composer install
```

## Configuration

Before the usage, you will need to configure at least the clients and server options.

### Server configuration
The server options are located into `quasar/Config.php` and represents a series of defines, like:
```php
/**
 * Define the global prefix.
 */
define('PREFIX', 'quasar_');

/**
 * Define the SocketIO Server port.
 */
define('SENDER_PORT', 2120);

/**
 * Define the WEB Server host.
 */
define('SERVER_HOST', '0.0.0.0');

/**
 * Define the WEB Server port.
 */
define('SERVER_PORT', 2121);
```
Usually, you can leave those options as is, specially for local testing. But in the case you intend to run multiple Quasar instances in the same server, every one of them should have their own dedicated ports and prefix.

### Clients configuration
The clients configuration is located in `quasar/Config/Clients.php` and should contain for every client its own entry, like:
```php
return array(
    'client1' => array(
        'name'   => 'Client Application',
        'appId'  => 'JPNSWIRFavLVLhjI25MXXVRyMHUjjeWI',
        'secret' => 'PBxOhgCQbfn03qJA0TH94fYDPiNKlpYq',
    ),
);
```
An client entry is defined by a unique slug as key, and an array of options: name, appId and secret.

The **name** field is informal only, and you can customize as you like.
The **appId** field represents the application's public ID, and should contain an 32 characters random string.
The **secret** field represents a random 32 characters secret key used by the authentication infrastructure.

The field values of **appId** and **secret**, configured in Quasar for the client, should be also configured in your Nova application, in the `app/Config/Broadcasting.php`, like:
```php
'quasar' => array(
    'driver'  => 'quasar',
    'appId'   => 'JPNSWIRFavLVLhjI25MXXVRyMHUjjeWI',
    'secret'  => 'PBxOhgCQbfn03qJA0TH94fYDPiNKlpYq',

    'host'    => 'quasar.dev',
    'port'    => 2121,
    
     // The SocketIO server hostname and port.
     'socket' => 2120,
),
```
Aditionally, you should configure the fields **host**, **port** and **socket** port which should point to the address where is located your Quasar instance and its ports.
