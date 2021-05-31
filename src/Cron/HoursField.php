<?php

declare(strict_types=1);

namespace Cron;

use DateTimeInterface;
use DateTimeZone;

/**
 * Hours field.  Allows: * , / -.
 */
class HoursField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 0;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 23;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(NextRunDateTime $date, $value): bool
    {
        $checkValue = (int) $date->format('H');
        $retval = $this->isSatisfied($checkValue, $value);
        if ($retval) {
            return $retval;
        }

        if (! $date->isMovingBackwards()) {
            // Did time just leap forward by an hour?
            $lastTransition = $date->getPastTransition();
            if (($lastTransition !== null) && ($lastTransition["ts"] > ($date->getTimestamp() - 3600))) {
                $dtLastOffset = clone $date;
                $dtLastOffset->modify("-1 hour");
                $lastOffset = $dtLastOffset->getOffset();

                $offsetChange = $lastTransition["offset"] - $lastOffset;
                if ($offsetChange >= 3600) {
                    $checkValue -= 1;
                    return $this->isSatisfied($checkValue, $value);
                } elseif ($offsetChange <= -3600) {
                    $checkValue += 1;
                    return $this->isSatisfied($checkValue, $value);
                }
            }
        } else {
            // Is time about to jump (from our backwards travelling pov) an extra hour?
            $nextTransition = $date->getPastTransition();
            if (($nextTransition !== null) && ($nextTransition["ts"] > ($date->getTimestamp() - 3600))) {
                $dtNextOffset = clone $date;
                $dtNextOffset->modify("-1 hour");
                $nextOffset = $dtNextOffset->getOffset();

                $offsetChange = $date->getOffset() - $nextOffset;
                if ($offsetChange >= 3600) {
                    $checkValue -= 1;
                    return $this->isSatisfied($checkValue, $value);
                }
            }
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null                  $parts
     */
    public function increment(NextRunDateTime $date, $invert = false, $parts = null): FieldInterface
    {
        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards and hour even
        // if DST will be changed between the hours.
        if (null === $parts || '*' === $parts) {
            $date->incrementHour();
            return $this;
        }

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        $hours = [];
        foreach ($parts as $part) {
            $hours = array_merge($hours, $this->getRangeForExpression($part, 23));
        }

        $current_hour = (int) $date->format('H');
        $position = $invert ? \count($hours) - 1 : 0;
        $countHours = \count($hours);
        if ($countHours > 1) {
            for ($i = 0; $i < $countHours - 1; ++$i) {
                if ((!$invert && $current_hour >= $hours[$i] && $current_hour < $hours[$i + 1]) ||
                    ($invert && $current_hour > $hours[$i] && $current_hour <= $hours[$i + 1])) {
                    $position = $invert ? $i : $i + 1;

                    break;
                }
            }
        }

        $target = (int) $hours[$position];
        $date->setHour($target);

        return $this;
    }
}
