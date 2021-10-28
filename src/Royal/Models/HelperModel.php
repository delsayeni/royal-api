<?php

namespace Royal\Models;

use Royal\Domain\Constants;

class HelperModel extends BaseModel
{
    public function getEventIdFromEventTicketId($event_ticket_id)
    {
        $eventTicketModel =  new EventTicketModel();
        if (!$eventTicketModel->where("event_ticket_id", $event_ticket_id)->exists()) {
            return false;
        }
        $event_ticket = $eventTicketModel->where("event_ticket_id", $event_ticket_id)->first();
        return $event_ticket["event_id"];
    }

    public function isEventTicketSaleExpired($event_ticket_id)
    {
        $event_id = $this->getEventIdFromEventTicketId($event_ticket_id);

        return $this->isEventExpired($event_id);
    }

    public function isEventExpired($event_id)
    {
        $eventControlModel = (new EventControlModel());
        if (!$eventControlModel->where("event_id", $event_id)->exists()) {
            return false;
        }
        $event_controls = $eventControlModel->where("event_id", $event_id)->first();
        $event_sale_stop_time = $event_controls["event_sale_stop_time"];

        return (time() > $event_sale_stop_time);
    }

    public function isEventTicketTransferable($event_ticket_id)
    {
        $event_id = $this->getEventIdFromEventTicketId($event_ticket_id);

        $eventControlModel = (new EventControlModel());
        if (!$eventControlModel->where("event_id", $event_id)->exists()) {
            return false;
        }
        $event_controls = $eventControlModel->where("event_id", $event_id)->first();
        $event_can_transfer_ticket = $event_controls["event_can_transfer_ticket"];

        return $event_can_transfer_ticket == Constants::EVENT_CAN_TRANSFER_TICKET;
    }

    public function isEventTicketRecallable($event_ticket_id)
    {
        $event_id = $this->getEventIdFromEventTicketId($event_ticket_id);

        $eventControlModel = (new EventControlModel());
        if (!$eventControlModel->where("event_id", $event_id)->exists()) {
            return false;
        }
        $event_controls = $eventControlModel->where("event_id", $event_id)->first();
        $event_can_recall_ticket = $event_controls["event_can_recall"];

        return $event_can_recall_ticket == Constants::EVENT_CAN_RECALL_TICKET;
    }
}
