<?php namespace Groff\Command;

abstract class Command
{

    /** @var \Groff\Command\ArgumentParser */
    private $parser;

    /** @var \Groff\Command\OptionCollection */
    private $options;

    /** @var \Groff\Command\EventHost */
    private $eventHost;

    /**
     * Provides default instantiation of dependencies and allows them to
     * be overridden for unit testing. Calls constructed hook for
     * implementing class and fires a notification.
     *
     * @param ArgumentParser   $parser
     * @param OptionCollection $options
     * @param EventInterface   $eventHost
     */
    public function __construct(
//                                                                                                                      @formatter:off
        ArgumentParser   $parser    = null,
        OptionCollection $options   = null,
        EventInterface   $eventHost = null
//                                                                                                                      @formatter:on
    )
    {

        if (is_null($parser)) {
            $parser = new ArgumentParser();
        }

        if (is_null($options)) {
            $options = new OptionCollection();
        }

        if (is_null($eventHost)) {
            $eventHost = new Observe\EventHost();
        }

        $this->parser    = $parser;
        $this->options   = $options;
        $this->eventHost = $eventHost;

        $this->constructed();
        $this->notify("constructed");
    }

    /**
     * Contains the main body of the command
     *
     * @return Int Status code - 0 for success
     */
    abstract function main();

    /**
     * Hook for the inheriting class to attach observers, or do whatever
     * else it would like to do in the constructor without having to
     * worry about implementing dependency injection
     *
     * @return void
     */
    protected function constructed()
    {
        //hook in inheriting class
    }

    /**
     * Hook for the inheriting class to define available options
     *
     * @return void
     */
    protected function addOptions()
    {
        //hook in inheriting class
    }

    /**
     * This is an instance of the template method pattern. It runs the entire Cli Command
     * but leaves the `main` method abstract to ensure it is implemented in the
     * subclass. An optional addOptions hook is also provided here.
     *
     * @return int Status code. 0 for success, other values indicate an error.
     */
    final public function run()
    {
        $this->notify("run");

        $this->addOptions();

        $this->addOption(new Option("h", false, "Prints this usage information.", "help"));

        $this->notify("options added");

        $this->populateOptions();

        $this->notify("options available");

        if ($this->option("help")) {
            return $this->printHelp();
        }

        $this->notify("pre main");

        $status = $this->main();

        $this->notify("shutdown");

        return $status;
    }

    /**
     * A Facade of OptionCollection. Declared final to make sure it
     * behaves as expected, since its used in the run method above.
     *
     * @param OptionInterface $option
     */
    final protected function addOption(OptionInterface $option)
    {
        $this->options->add($option);
    }

    /**
     * Returns an option's value. Facade of OptionCollection.
     *
     * @param $query string An option's name or alias
     *
     * @return mixed
     */
    final public function option($query)
    {
        $option = $this->options->find($query);

        return $option->getValue();
    }

    /**
     * Sends an event to any registered listeners.
     *
     * @param       $name
     * @param array $data
     */
    final public function notify($name, $data = array())
    {
        $this->eventHost->notify($name, $data);
    }

    /**
     * Attaches an event listener
     *
     * @param Observe\ListenerInterface $observer
     */
    final public function attach(Observe\ListenerInterface $observer)
    {
        $this->eventHost->attach($observer);
    }

    /**
     * Detaches an event listener
     *
     * @param Observe\ListenerInterface $observer
     */
    final public function detach(Observe\ListenerInterface $observer)
    {
        $this->eventHost->detach($observer);
    }

    /**
     * Provides CLI arguments. Abstracted since it uses an ugly global variable.
     * Also allows a subclass to provide its own options.
     *
     * @return array
     */
    protected function provideFlatOptions()
    {
        global $argv;

        return $argv;
    }

    /**
     * Gets parsed options and uses them to populate options in the options collection
     */
    private function populateOptions()
    {
        $flatOptions = $this->provideFlatOptions();

        $this->parser->parseInput($flatOptions);

        $parsed = $this->parser->getOptions();

        foreach ($parsed as $optionName => $optionValue) {
            //ignores any options the user sent which were not defined
            $this->options->setValueIfExists($optionName, $optionValue);
        }
    }

    /**
     * Prints help output
     *
     * @return int Status code
     */
    private function printHelp()
    {
        $script = $this->parser->getScriptName();
        echo "Usage: [options] $script \n\n";

        /** @var $option Option */
        foreach ($this->options as $option) {
            echo " " . $option . "\n";
        }

        echo "\n";

        $this->notify("output");

        $this->notify("shutdown");

        return 0;
    }

} 