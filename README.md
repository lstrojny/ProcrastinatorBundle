# ProcrastinatorBundle for Symfony2: do stuff later

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


        $this->get('procrastinator')->register(
            new Deferred(
                'sendMail',
                function() use ($mailer, $message) {
                    $mailer->send($message);
                },
                OrmEvents::postFlush
        );


        $em = $this->get('doctrine.orm.default_entity_manager');
        $em->persist($entry);
        $em->flush();
    }
}
```
