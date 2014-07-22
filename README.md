# Fiendish-Bundle

Fiendish-bundle allows you to write daemons within Symfony2, and control their
execution.

[API Documentation](http://americancouncils.github.com/fiendish-bundle/annotated.html)

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
        "americancouncils/fiendish-bundle": "dev-master"
    }

And add both Fiendish and the RabbitMQ bundle to your `app/AppKernel.php`
bundles list:

    new OldSound\RabbitMqBundle\OldSoundRabbitMqBundle(),
    new AC\FiendishBundle\ACFiendishBundle()

Then you'll need to set up the `Process` table in your database. For now,
that means manually installing and running the migration file
found in the project's DoctrineMigrations folder.

Finally, you need to add some settings to supervisor to organize
the daemons for your specific app. Here's an example of a config
`/etc/supervisor/conf.d/foobar.conf` for a project called Foobar:

    [program:foobar_master]
    command=/usr/bin/php /var/www/foobar/app/console fiendish:master-daemon foobar
    redirect_stderr=true

    [group:foobar]

The group section is deliberately empty; Fiendish will
be adding and removing processes in that group dynamically.

You'll also want to add a corresponding section to your Symfony
config file:

    fiendish:
        groups:
            foobar:
                process_user: "www-data"

`process_user` is the UNIX user that your daemons will run as.

## Writing a Daemon

Daemons are implemented as classes that derive from `BaseDaemon`.
Here's an example daemon for Foobar:

```php
namespace SomeRandomCoder\FoobarBundle\Daemon;

use AC\FiendishBundle\Daemon\BaseDaemon;

class UselessDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while(true) {
            $this->heartbeat();

            print("FOO " . $arg['phrase'] . "!\n");
            sleep(1);
            print("BAR " . $arg['phrase'] . "!\n");
            sleep(1);
        }
    }
}
```

The `run` method is called when your daemon starts. If your daemon is
meant to stay up all the time, then `run` should never return. It also needs
to call the `heartbeat` method regularly, every few seconds or so. If a long
enough time passes between heartbeats (by default, 30 seconds), your daemon
will be assumed frozen and forcibly restarted.

The daemon has full access to your Symfony app's services. You can get
to the container by calling `$this->getContainer()`.

You can also pass arguments to your daemons when you start them. Any
JSON-serializable object can be used. In the example above an associative
array with a single key was passed in.

## Starting and Stopping Daemon Processes

To start a daemon process, use the Group service as shown:

```php
use SomeRandomCoder\FoobarBundle\Daemon\UselessDaemon;

$container = $this->getContainer();
$kernel = $container->get('kernel');
$group = $container->get('fiendish.groups.foobar');
$proc = $group->newProcess(
    "useless_thing", // Name prefix, to help identify this process
    UselessDaemon::toCommand($kernel), // The command to execute
    ["phrase" => "fries and a shake"] // The argument for run()
);
$procName = $proc->getProcName(); // Needed to access this Process later
$group->applyChanges(); // This call does not block
```

When applyChanges is called, the master daemon wakes up and
adds all new processes to the Supervisor group and starts
them up.

Stopping a running daemon is similar:

```php
$container = $this->getContainer();
$group = $container->get('fiendish.groups.foobar');
$proc = $group->getProcess($procName); // This is the procName you got earlier...
$group->removeProcess($proc);
$group->applyChanges();

```

## Debugging

Supervisor will keep track of everything printed out by your daemons
and all activity related to them starting and stopping.
Your best bet for figuring out any problems with your daemons
is to use the Supervisor console:

    $ sudo supervisorctl
    > status
    foobar_master              RUNNING    pid 8263, uptime 1:35:03
    foobar:useless_thing.373771873643687276    RUNNING   pid 8267, uptime 1:28:28
    > tail -f foobar:useless_thing.373771873643687276
    FOO fries and a shake!
    BAR fries and a shake!
    FOO fries and a shake!
    BAR fries and a shake!
    ...  (All print output and PHP error output ends up here) ...

(Thankfully, Supervisor's console has tab-completion, so there's no need
to type out the long random numbers used to uniquely tag processes.)

## Non-PHP Daemons

You can write your daemon processes in a language other than PHP by using the
`ExternalDaemon` class. Implement the `getExternalCommand` method, returning
a resource path or absolute path to the executable:

```php
namespace JoeCoder\MyBundle\Daemon;

use AC\FiendishBundle\Daemon\ExternalDaemon;

class MyPythonAppDaemon extends ExternalDaemon
{
    public function getExternalCommand()
    {
        return "@JoeCoderMyBundle/Resources/scripts/myapp.py";
    }
}
```

The third argument passsed to startProcess will be JSON-encoded and given to your process
as the first command-line argument.

Your daemon still has to emit heartbeats at regular intervals. To help with this,
two environment variables are set:

* `FIENDISH_HEARTBEAT_ROUTING_KEY`
* `FIENDISH_HEARTBEAT_MESSAGE`

To emit a heartbeat, publish the given message to default exchange with the given routing key on AMQP.
