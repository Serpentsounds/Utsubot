<?php
/**
 * Utsubot - Web.php
 * User: Benjamin
 * Date: 25/11/14
 */

namespace Utsubot\Web;

use Utsubot\Help\HelpEntry;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\bold;


/**
 * Class DNSException
 *
 * @package Utsubot\Web
 */
class DNSException extends WebModuleException {

}

/**
 * Class DNS
 *
 * @package Utsubot\Web
 */
class DNS extends WebModule {

    /**
     * DNS constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        //  Command triggers
        $dns = new Trigger("dns", [$this, "dns"]);
        $this->addTrigger($dns);

        $rdns = new Trigger("rdns", [$this, "rdns"]);
        $this->addTrigger($rdns);

        //  Help entries
        $dnsHelp = new HelpEntry("Web", $dns);
        $dnsHelp->addParameterTextPair("HOST", "Perform a DNS lookup on HOST, returning the resolved IP address.");
        $this->addHelp($dnsHelp);

        $rdnsHelp = new HelpEntry("Web", $rdns);
        $rdnsHelp->addParameterTextPair("IP", "Perform a Reverse DNS lookup on IP, returning the resolved hostname and geolocation information.");
        $this->addHelp($rdnsHelp);

    }


    /**
     * Lookup DNS for a hostname. Get A records (IPV4), AAAA records (IPV6), and CNAME records (alias)
     *
     * @param IRCMessage $msg
     * @throws DNSException If hostname is invalid, or no dns records are found
     *
     * @usage !dns <host>
     */
    public function dns(IRCMessage $msg) {
        //	Make sure the host isn't bogus before attempting dns
        if (!preg_match('/^([A-Z0-9\-]+\.)+[A-Z0-9\-]+\.?$/i', $msg->getCommandParameterString(), $match))
            throw new DNSException("Invalid hostname format.");

        //	Append trailing . to speed up the return in some cases
        if (substr($match[0], -1) != ".")
            $match[0] .= ".";

        //	dns_get_record will throw an error if lookup fails, so @suppress it and throw an exception instead
        $records = @dns_get_record($match[0], DNS_A + DNS_AAAA + DNS_CNAME);
        if (!$records)
            throw new DNSException("No DNS record found.");

        $result = [ ];

        //	Filter DNS record array based on record type
        foreach ($records as $entry) {
            switch ($entry['type']) {

                case "A":
                    $result['A'][] = bold($entry['ip']);
                    break;

                case "AAAA":
                    $result['AAAA'][] = bold($entry['ipv6']);
                    break;

                case "CNAME":
                    $result['CNAME'][] = bold($entry['target']);
                    break;
            }
        }

        //	Join multiple entries of the same type with a comma
        $response = [ ];
        foreach ($result as $type => $arr)
            $response[] = implode(", ", $arr)." [$type]";

        $response = bold($match[0])." resolved to ".implode(self::separator, $response);
        $this->respond($msg, $response);
    }


    /**
     * Do a reverse DNS lookup on an IP address
     *
     * @param IRCMessage $msg
     * @throws DNSException
     *
     * @usage !rdns <ipaddress>
     */
    public function rdns(IRCMessage $msg) {
        $ip = $msg->getCommandParameterString();

        if (!preg_match('/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $ip, $match))
            throw new DNSException("Invalid IP address format.");

        $json    = resourceBody("http://ip-api.com/json/$ip");
        $results = json_decode($json, true);

        if ($results['status'] != "success")
            throw new DNSException("Lookup failed.");

        $output = array(
            sprintf(
                "%s: %s [%s]",
                bold("Country"),
                $results['country'],
                $results['countryCode']
            ),
            sprintf(
                "%s: %s [%s]",
                bold("Region"),
                $results['regionName'],
                $results['region']
            ),
            sprintf(
                "%s: %s [%s]",
                bold("City"),
                $results['city'],
                $results['zip']
            ),
            sprintf(
                "%s: %s°%s, %s°%s",
                bold("Location"),
                round(abs($results['lat']), 2),
                (($results['lat'] < 0) ? "S" : "N"),
                round(abs($results['lon']), 2),
                (($results['lon'] < 0) ? "W" : "E")
            ),
            sprintf(
                "%s: %s",
                bold("Time Zone"),
                str_replace("_", " ", $results['timezone'])
            ),
            sprintf(
                "%s: %s",
                bold("ISP"),
                $results['isp']
            )
        );

        $this->respond($msg, implode(self::separator, $output));
    }

}