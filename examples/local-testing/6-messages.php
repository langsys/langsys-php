<?php
require __DIR__ . '/bootstrap.php';

use Langsys\SDK\Messages\HasAppMessageTemplate;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\ServerMessage;

final class QuotaExceeded implements HasAppMessageTemplate
{
    public int $limit;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
    }

    public function template()
    {
        return "You have used all {limit} of this month's requests.";
    }

    public function code()
    {
        return 'quota_exceeded';
    }
}

$catalog = new MessageCatalog();
$catalog->addMessage(QuotaExceeded::class, 'app/QuotaExceeded.php');
echo json_encode($catalog->templates()), ' problems: ', json_encode($catalog->problems()), "\n";

echo json_encode(ServerMessage::fromApp(new QuotaExceeded(500))), "\n";
