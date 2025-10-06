Flow & Neos Task Scheduler
==========================

A basic tasks scheduler inspired by cron for your Flow and Neos project.

Create a Task object
--------------------

You need to implement the ``Ttree\Scheduler\Task\TaskInterface``::

```php
class MyTask implements \Ttree\Scheduler\Task\TaskInterface {
	/**
	 * @param array
	 * @return void
	 */
	public function execute(array $arguments = array()) {
		// ...
	}
}
```

Dynamic Tasks
-------------

You can schedule your own Task object, by adding a ```Ttree\Scheduler\Annotations\Schedule`` annotation to your class::

```php
use Ttree\Scheduler\Annotations as Scheduler;
/**
 * @Scheduler\Schedule(expression="* * * * *")
 */
class MyTask implements \Ttree\Scheduler\Task\TaskInterface {
	// ...
}
```

or

```php
use Ttree\Scheduler\Annotations as Scheduler;

#[Scheduler\Schedule(["expression" => "* * * * *"])]
class MyTask implements \Ttree\Scheduler\Task\TaskInterface {
	// ...
}
```

This task will be executed every minute. Dynamic task do not support arguments, the ``$arguments`` of the execute method
is always an empty array.

If your expression contains slashes, you have to double-escape them. I.e. run the task every 5 minutes: `@Scheduler\Schedule(expression="*\\/5 * * * *")`

You can also add a description to your task using the Meta annotation::

```php
use Ttree\Scheduler\Annotations as Scheduler;
/**
 * @Scheduler\Meta(description="Describes your task.")
 */
```

or

```php
use Ttree\Scheduler\Annotations as Scheduler;
#[Scheduler\Meta(["description" => "Describes your task."])]
```

Persisted Tasks
---------------

You can also create persisted tasks. Persisted tasks support execution argument. You can register the same task object
multiple times, if your arguments are different between each task. You can pass a valid JSON arguments array:

    flow task:register --expression "* */3 * * *" --task "Ttree\Aggregator\Task\AggregatorTask" --arguments '{"node": "af97b530-0c70-7b87-3cf4-f9a611f88c18"}'

Available Configuration Options
-------------------------------

| Option                 | Default | Description                                                                                                                                   |
|------------------------|---------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| allowParallelExecution | true    | If the scheduler command is executed while the scheduler is already running tasks, the second scheduler waits until the first one is finished |


Available CLI helpers
---------------------

List all available tasks (dynamic and persisted):

    flow task:list

Run all due tasks (dynamic and persisted):

	flow task:run

Directly run a single task:

	flow task:runSingle --task [identifier]

Enable a persisted task:

	flow task:enable --task [identifier]

Disable a persisted task:

	flow task:disable --task [identifier]

TODO
----

Feel free to open issue if you need a specific feature and better send a pull request. Here are some idea for future
improvements:

* A Neos backend module to have an overview of tasks
* Asynchronous task handling or multi thread (pthread support)

Acknowledgments
---------------

This package is inspired by [Famelo.Scheduler - by mneuhaus](https://github.com/mneuhaus/Famelo.Scheduler/).

Development sponsored by [ttree ltd - neos solution provider](http://ttree.ch).

License
-------

Licensed under GPLv2+, see [LICENSE](LICENSE)