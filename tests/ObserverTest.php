<?php

use Ornament\Model;

class ObserverModel implements SplObserver
{
    use Ornament\Storage;
    use Ornament\Observer;

    public $id;
    public $name;
    public $comment;
    
    public function __construct()
    {
        global $adapter;
        $this->addAdapter($adapter, 'dummy', ['id', 'name', 'comment']);
    }

    public function calledFromNotify(SubjectModel $subject)
    {
        echo 'yes';
    }

    public function alsoCalledFromNotify(SubjectModel $subject)
    {
        echo 'also';
    }

    public function neverCalled(DummyModel $subject)
    {
        echo 'noop';
    }
}

class SubjectModel implements SplSubject
{
    use Ornament\Storage;
    use Ornament\Subject;

    public $id;
    public $name;
    public $comment;
    
    public function __construct()
    {
        global $adapter;
        $this->addAdapter($adapter, 'dummy', ['id', 'name', 'comment']);
    }
}

class ObserverTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Ornament\Observer
     * @covers Ornament\Subject
     */
    public function testObserving()
    {
        $observer = new ObserverModel;
        $subject = new SubjectModel;
        $subject->attach($observer);
        $subject->save();
        $another = new SubjectModel;
        $another->attach($observer);
        // Don't call save, nothing should happen...
        $this->expectOutputString('yesalso');
    }
}
