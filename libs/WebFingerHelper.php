<?php

namespace Lib;

/**
 *
 *
 *
 *
 * @author Matthias Pfefferle <matthias@pfefferle.org>
 */
class WebFingerHelper
{
    /**
     * @var array
     */
    protected $relMappings = array(
                                "http://webfinger.net/rel/profile-page" => "Profile Page",
                                "http://webfinger.net/rel/avatar" => "Avatar"
                            );

    /**
     * @var \Net_WebFinger_Reaction
     */
    protected $react = null;

    /**
     * Constructor
     *
     * @param Net_WebFinger_Reaction $react the Reaction Object of the WebFinger parser
     */
    public function __construct($react)
    {
        $this->react = $react;
    }

    /**
     * Converts rel values into nice names
     *
     * @param string $rel a link relation
     *
     * @return string a nice name
     */
    public function getNicenameByRel($rel)
    {
        if (array_key_exists($rel, $this->relMappings)) {
            return $this->relMappings[$rel];
        }

        return $rel;
    }

    /**
     * Beautify the hCard keys
     *
     * @param string $key the hCard key
     *
     * @return string a nicer key name
     */
    public function beautifyHCardKey($key)
    {
        return ucfirst($key) . ":";
    }

    /**
     * Transform links array of reaction object into a valid
     * Symfony\Component\Console\Helper\Table array
     *
     * @return array an optimized array
     */
    public function getLinksTableView()
    {
        $links = array();

        foreach ($this->react as $link) {
            $type = $this->getNicenameByRel($link->rel);
            $link = ($link->href ? $link->href : $link->template);
            $link = mb_strimwidth($link, 0, 100, "...");

            $links[] = array($type, $link);
        }

        return $links;
    }

    /**
     * Transform hCard profile array into a valid
     * Symfony\Component\Console\Helper\Table array
     *
     * @return array an optimized array
     */
    public function getProfileTableView()
    {
        $hCard = $this->getRepresentativeHCard();

        if (!$hCard) {
            return array(array("no", "data"));
        }

        $profile = array();

        foreach ($hCard['properties'] as $key => $value) {
            if (in_array($key, array("type"))) {
                continue;
            }

            if (is_array($value)) {
                $first = current($value);
                if (is_array($first)) {
                    if (array_key_exists('value', $first)) {
                        $value = $first['value'];
                    } else {
                        $value = $value[0];
                    }

                    continue;
                }

                $value = implode(PHP_EOL, $value);
            }

            $profile[] = array($this->beautifyHCardKey($key), $value);
        }

        return $profile;
    }

    /**
     * Checks HTML code to find a representative hCard
     *
     * @return string|null the hCard or null
     */
    public function getRepresentativeHCard()
    {
        foreach ($this->react as $link) {
            if (in_array($link->rel, array("http://microformats.org/profile/hcard", "http://webfinger.net/rel/profile-page"))) {
                $mfs = \Mf2\fetch($link->href);

                if ($mfs && $hCard = \BarnabyWalters\Mf2\getRepresentativeHCard($mfs, $link->href)) {
                    return $hCard;
                }
            }
        }

        return null;
    }
}
