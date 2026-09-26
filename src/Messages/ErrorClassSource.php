<?php

namespace Langsys\SDK\Messages;

/**
 * Error classes that declare their message, for an app without a framework
 * binding: each class carries its template as a MESSAGE (or TEMPLATE)
 * constant and a public property per marker, and may carry its own identifier
 * as CODE, which is passed through as the entry's code (MSG-2).
 */
final class ErrorClassSource implements MessageSource
{
    /** @var string[] */
    private $classes;

    /**
     * @param string[] $classNames
     */
    public function __construct(array $classNames)
    {
        $this->classes = array_values($classNames);
    }

    /**
     * @param MessageCatalog $catalog
     * @return void
     */
    public function collect(MessageCatalog $catalog)
    {
        foreach ($this->classes as $class) {
            $class = ltrim((string) $class, '\\');

            if (!class_exists($class)) {
                $catalog->problem($class, 'is not a loadable class', 'check the class name and its autoloading');
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $constant = $reflection->hasConstant('MESSAGE') ? 'MESSAGE' : ($reflection->hasConstant('TEMPLATE') ? 'TEMPLATE' : null);

            if ($constant === null) {
                $catalog->problem($class, 'declares no MESSAGE or TEMPLATE', 'declare the template it sends as a MESSAGE constant');
                continue;
            }

            // Constants inherit silently: a class that forgot its own message
            // answers with its parent's, and two failures become one.
            $declaring = $reflection->getReflectionConstant($constant)->getDeclaringClass()->getName();
            if ($declaring !== $reflection->getName()) {
                $catalog->problem($class, "inherits its $constant from $declaring", "declare its own $constant so the failure is its own");
            }

            $template = $reflection->getConstant($constant);
            $properties = array_map(function (\ReflectionProperty $property) {
                return $property->getName();
            }, $reflection->getProperties());

            foreach (MessageTemplate::markers($template) as $marker) {
                if (!in_array($marker, $properties, true)) {
                    $catalog->problem($class, "uses the marker {{$marker}} but has no \$$marker property to fill it", "add a public \$$marker property");
                }
            }

            $catalog->add($template, $class);
        }
    }
}
