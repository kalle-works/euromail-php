<?php

namespace EuroMail\Http;

interface TransportInterface
{
    public function send(Request $request): Response;
}
