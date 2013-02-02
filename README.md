# Fiendish-Bundle

Fiendish-bundle allows you to write daemons within Symfony2, and control their
execution.

[API Documentation](http://davidmikesimon.github.com/fiendish-bundle/annotated.html)

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

And add both Fiendish and the RabbitMQ bundle to your `app/AppKernel.php`
bundles list:
    
    new OldSound\RabbitMqBundle\OldSoundRabbitMqBundle(),
    new DavidMikeSimon\FiendishBundle\DavidMikeSimonFiendishBundle()

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

use DavidMikeSimon\FiendishBundle\Daemon\BaseDaemon;

class UselessDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while(true) {
            print("FOO " . $arg->phrase . "!\n");
            sleep(1);
            print("BAR " . $arg->phrase . "!\n");
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
JSON-serializable object can be used. In the example above an associative
array with a single key was passed in.

Be aware that PHP's json deserialization turns associative arrays
(i.e. arrays with string keys) into objects, so that anything put to `$arg["xyz"]`
outside the daemon will need to be accessed as `$arg->xyz` within.

## Starting and Stopping Daemon Processes

To start a daemon process, use the Group service as shown:

```php
use SomeRandomCoder\FoobarBundle\Daemon\UselessDaemon;

$container = $this->getContainer();
$rootDir = $container->get('kernel')->getRootDir();
$group = $container->get('david_mike_simon_fiendish.groups.foobar');
$proc = $group->newProcess(
    "useless_thing", // Name prefix, to help identify this process
    UselessDaemon::toCommand($rootDir), // The command to execute
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
$group = $container->get('david_mike_simon_fiendish.groups.foobar');
$proc = $group->getProcess($procName); // Hopefully you kept procName earlier...
$group->removeProcess($proc);
$group->applyChanges();

```

## Debugging

Supervisor will keep track of everything printed out by your daemons
and all activity related to them starting and stopping.
Yur best bet for figuring out any problems with your daemons
is to use the Supervisor console:

    $ sudo supervisorctl
    > status
    foobar_master              RUNNING    pid 8263, uptime 1:35:03
    foobar:useless_thing.37    FATAL
    > tail -f foobar:useless_thing.37
    FOO fries and a shake!
    BAR fries and a shake!
    FOO fries and a shake!
    BAR fries and a shake!
    ...  (All print output and PHP error output ends up here) ...

(The number 37 in the process name above refers to the Process row
with id 37.)
