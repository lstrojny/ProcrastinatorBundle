# ProcrastinatorBundle for Symfony2: do stuff later [![Build Status](https://secure.travis-ci.org/lstrojny/ProcrastinatorBundle.svg)](http://travis-ci.org/lstrojny/ProcrastinatorBundle) [![Dependency Status](https://www.versioneye.com/user/projects/523ed7f8632bac1b1400bff0/badge.png)](https://www.versioneye.com/user/projects/523ed7f8632bac1b1400bff0) [![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/lstrojny/procrastinatorbundle.svg)](http://isitmaintained.com/project/lstrojny/procrastinatorbundle "Average time to resolve an issue") [![Percentage of issues still open](http://isitmaintained.com/badge/open/lstrojny/procrastinatorbundle.svg)](http://isitmaintained.com/project/lstrojny/procrastinatorbundle "Percentage of issues still open")

Symfony2 integration for [Procrastinator](https://github.com/lstrojny/Procrastinator)

### Example usage in controller to execute event only if the postFlush event in Doctrine occured
```php
<?php
use Procrastinator\Deferred\DoctrineEventConditionalDeferred as Deferred;
use Doctrine\ORM\Events as OrmEvents;

class MyController ...
{
    public function sendMailAction()
    {
        $entry = new Entity();
        $entry->setText('hello world');

        $message = Message::newInstance()
                    ->setSubject('hello')
                    ->setBody('new entry');
        $mailer = $this->get('mailer');


        $procrastinator->register(
            $procrastinator
                ->newDeferred()
                ->ifDoctrineEvent(OrmEvents::postFlush)
                ->name('sendMail')
                ->call(function() use ($mailer, $message) { $mailer->send($message); })
                ->build()
        );


        $em = $this->get('doctrine.orm.default_entity_manager');
        $em->persist($entry);
        $em->flush();
    }
}
```
