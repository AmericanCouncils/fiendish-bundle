#!/usr/bin/python -u

# Python implementation of Fixtures/Daemon/TestDaemon

import time, sys, os, pika, json

spec = json.loads(sys.argv[1])
content = spec['arg']['content']
heartbeat_key = os.environ.get('FIENDISH_HEARTBEAT_ROUTING_KEY')
heartbeat_msg = os.environ.get('FIENDISH_HEARTBEAT_MESSAGE')

def heartbeat():
    connection = None
    try:
        connection = pika.BlockingConnection()
    except pika.exceptions.AMQPError as e:
        pass # Don't worry about transient connection failures

    try:
        channel = connection.channel()
        channel.basic_publish(exchange="", routing_key=heartbeat_key, body=heartbeat_msg)
    except pika.exceptions.AMQPError as e:
        pass # Or transient delivery failures
    finally:
        try:
            connection.close()
        except pika.exceptions.AMQPError as e:
            pass # Or disconnection failures

while True:
    print content + "omatic"

    if content != "vampire":
        heartbeat()

    time.sleep(1)
