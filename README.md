TYPO3 Flow & Neos Task Scheduler
================================

A basic tasks scheduler inspired by cron for your TYPO3 Flow and Neos project.

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

You can schedule your own Task object, by adding a `Ttree\Scheduler\Annotations\Schedule`` annotation to your class::

```php
use Ttree\Scheduler\Annotations as Scheduler;
/**
 * @Scheduler\Schedule(expression="* * * * *")
 */
class MyTask implements \Ttree\Scheduler\Task\TaskInterface {
	// ...
}
```

This task will be executed every minute. Dynamic task do not support arguments, the ``$arguments`` of the execute method
is always an empty array.


Persisted Tasks
---------------

You can also create persisted tasks. Persisted tasks support execution argument. You can register the same task object
multiple times, if your arguements are differents between each task. You can pass a valid JSON arguments array:

    flow task:create --expression "* */3 * * *" --task "Ttree\Aggregator\Task\AggregatorTask" --arguments '{"node": "af97b530-0c70-7b87-3cf4-f9a611f88c18"}'
    
Available CLI helpers
---------------------

List all available tasks (dynamic and persisted):

    flow task:list
    
Run all due tasks (dynamic and persisted):

	flow task:run
	
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