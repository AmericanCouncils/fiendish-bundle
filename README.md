# Fiendish

Fiendish allows you to write daemons within Symfony2, and control their
execution.

## Installation

First you need to install and set up these non-PHP dependencies:
* [RabbitMQ](http://www.rabbitmq.com)
* [Supervisor](http://supervisord.org/)
* [Twiddler](https://github.com/mnaberez/supervisor_twiddler)

You will also need to make sure that you have the PHP executable
available from the command line. On Ubuntu, that means installing
the `php5-cli` package, and on other distributions it's most
likely something similar.

Next, install fiendish-bundle into your Symfony2 app via composer:

    
    "require": {
        ...
        "davidmikesimon/fiendish-bundle": "dev-master"
    }

Then you'll need to set up the `Process` table in your database. For now,
that means manually installing and running the migration file
found in the project's DoctrineMigrations folder.

Finally, you need to add some settings to supervisor to organize
the daemons for your specific app. Here's an example of a config
`/etc/supervisor/conf.d/foobar.conf` for a project called Foobar:

    [program:foobar_master]
    command=/usr/bin/php /var/www/foobar/app/console fiendish:master-daemon foobar
    redirect_stderr=true

    [group:beatbox]

The group section is deliberately empty in the config; Fiendish will
be adding and removing processes in that group dynamically.

## Writing a Daemon

Daemons are implemented as classes that derive from `Fiendish\Daemon\BaseDaemon`.
Here's an example daemon for Foobar:

```php
namespace SomeRandomCoder\FoobarBundle\Daemon;

use DavidMikeSimon\FiendishBundle\Daemon\BaseDaemon;

class UselessDaemon extends Daemon
{
    public function run($arg = null)
    {
        while(true) {
            print("FOO $arg!\n");
            sleep(1);
            print("BAR $arg!\n");
            sleep(1);
        }
    }
}
```

The `run` method is called when your daemon starts. If your daemon is
meant to stay up all the time, then `run` should never return.

The daemon has full access to your Symfony app's services. You can get
to the container from within `run` by calling `$this->getContainer()`.

You can also pass arguments to your daemons when you start them. Any
JSON-serializable object can be used. In the example above a simple
string is expected, but a realistic app would probably expect an array,
which can in turn be nested arbitrarily deep with other JSON-serializable
objects. Be aware that PHP's json deserialization turns associative arrays
(i.e. arrays with string keys) into objects, so that what was set via `$arg["xyz"]`
outside the daemon will need to be accessed as `$arg->xyz` within.

## Starting and Stopping Daemon Processes

To start a daemon process, persist a Process object to the database:

```php
use DavidMikeSimon\FiendishBundle\Entity\Process;
use DavidMikeSimon\FiendishBundle\Daemon\MasterDaemon;

$proc = new Process(
    "foobar", // Group name
    "useless_thing", // Name of this specific process
    "SomeRandomCoder\FoobarBundle\Daemon\UselessDaemon", // The daemon class
    "fries and a shake" // The argument to be passed to run
);
$em->persist($proc);
$em->flush();
MasterDaemon::sendSyncRequest("foobar"); // This call does not block
```

The master daemon for the group "foobar" will recieve that request via RabbitMQ.
During a sync, the master daemon will add and remove processes from the Supervisor
group as necessary to match the processes listed in the table.

To stop a daemon process, delete the Process from the table and send another
sync request. Supervisor's state will be updated, and your daemon process
will recieve a SIGTERM.

## Debugging

Supervisor will keep track of everything printed out by your daemons,
so your best bet for figuring out problems with your daemons crashing
is to use the Supervisor console:

    $ sudo supervisorctl
    > status
    foobar_master              RUNNING    pid 8263, uptime 1:35:03
    foobar:useless_thing.37    FATAL
    > tail foobar:useless_thing.37
    FOO fries and a shake!
    BAR fries and a shake!
    FOO fries and a shake!
    BAR fries and a shake!
    ...  (All print output and PHP error output ends up here) ...
