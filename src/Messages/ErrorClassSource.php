<?php

namespace Langsys\SDK\Messages;

/**
 * Error classes that declare their message, for an app without a framework
 * binding: each class carries a CODE and a MESSAGE (or TEMPLATE), and a public
 * property per marker, as langsys4's ApiError and FieldError do.
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

            // Constants inherit silently: a class that forgot its own CODE answers
            // with its parent's, and two failures become one an app cannot tell apart.
            foreach (['CODE', $constant] as $name) {
                if (!$reflection->hasConstant($name)) {
                    $catalog->problem($class, "declares no $name", "declare its own $name");
                    continue;
                }

                $declaring = $reflection->getReflectionConstant($name)->getDeclaringClass()->getName();

                if ($declaring !== $reflection->getName()) {
                    $catalog->problem($class, "inherits its $name from $declaring", "declare its own $name so the failure is its own");
                }
            }

            if ($reflection->hasConstant('CODE') && !MessageCodes::isSlug($reflection->getConstant('CODE'))) {
                $catalog->problem($class, "its code '" . $reflection->getConstant('CODE') . "' is not a snake_case slug", 'use a stable snake_case code such as too_short');
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
