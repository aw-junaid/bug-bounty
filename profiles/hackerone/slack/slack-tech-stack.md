
# Diagram.

<img width="1440" height="1338" alt="image" src="https://github.com/user-attachments/assets/590bdcf9-01d5-42df-8eb4-644c0291072a" />


Slack's Technology Architecture
-------------------------------

---

## The big picture: how it evolved

Slack didn't start as the complex distributed system it is today. It was first built on the LAMP stack — Linux, Apache, MySQL, and PHP — which was fast to deploy and got the job done early on. But as millions of users joined, that simple foundation cracked under the pressure, and Slack had to reinvent each layer one at a time.

---

## Frontend: three platforms, one codebase spirit

The web app migrated from a CoffeeScript application written with vanilla DOM APIs to a modern ES6 + async/await React application, and is currently incrementally moving to TypeScript.

For desktop, Slack takes a hybrid approach where it ships some of the assets as part of the app, but most of the assets and code are loaded remotely. This is clever because it means you get the speed of a native app at startup, but updates roll out to everyone the moment they connect — no reinstalling required.

On mobile, tech debt in Slack's mobile codebases had slowed development down enough to affect product roadmaps, and it took a major refactor to address it — a cross-functional initiative to Stabilize, Modularize, and Modernize the codebase. The result is a loosely coupled modular architecture in native Swift (iOS) and Kotlin (Android).

---

## Backend: the PHP monolith and its growing family

Client applications make their interactions with a big PHP monolith hosted in Amazon's US-East data center, which is backed by a MySQL database tier, and there is an asynchronous job queue system where Slack defers activities like unfurling links or indexing messages for search.

Hack SQL Fake is a library used to simulate MySQL for unit tests, and XHP enables type-safe, async server-side rendered HTML — it shares a history with React's JSX. So Slack's backend is essentially a PHP-family language (Hack) that has grown much more sophisticated over time, while keeping the same conceptual structure.

For async work, Slack added Kafka in front of Redis, leaving the existing queuing interface in place. A stateless service called Kafkagate was developed in Go to enqueue jobs to Kafka via HTTP POST. JQRelay, another stateless service, relays jobs from Kafka to Redis, ensuring only one relay process is assigned to each topic.

---

## Database: why Vitess was a game-changer

This is one of the most interesting engineering stories in Slack's history. Availability, performance, and scalability in the datastore layer is critical for Slack — every message sent is persisted before it is sent across the real-time WebSocket stack and shown to other members of the channel. Durability always comes before delivery.

Vitess is a sharding and topology management solution that sits on top of MySQL. The application connects to a routing tier called VtGate. VtGate knows the set of tables in the configuration, which backend servers are hosting those tables, and which column a given table is sharded by — allowing users/tables to be sharded by user ID, channels by channel ID, and workspaces by workspace ID. All of that routing knowledge is no longer in the PHP code.

Think of Vitess as a smart traffic cop sitting in front of many MySQL servers. Before Vitess, the PHP code itself had to know "this workspace lives on shard 7" — which was fragile and hard to scale. After Vitess, the PHP code just queries normally and VtGate figures out the routing transparently.

---

## Real-time: WebSockets, consistent hashing, and Flannel

Over one hundred types of events come across the WebSocket connection, including new messages, typing indicators, file uploads, thread replies, user presence changes, and user profile changes.

The key insight in Slack's real-time design is **consistent hashing**: consistent hashing maps Slack channels to gateway servers. The gateway server is a stateful in-memory service that pushes chat messages to the client over WebSockets. This means every member of a channel connects to the *same* gateway server, turning message delivery into a simple in-memory broadcast rather than a complex distributed operation.

For startup performance, Slack clients connect to Flannel, an application-level caching service developed in-house and deployed at edge points-of-presence, which gathers the full client startup data and opens a WebSocket connection back to Slack's servers in AWS regions. Flannel offers lower latency for subsequent fetches of a workspace snapshot because it stores data in memory — sequentially accessing data in memory is at least 80× faster than going to disk.

---

## The message flow in plain English

Here's the journey of a single message you send: your client fires an HTTP POST to the PHP server → PHP writes it to MySQL via Vitess (durability first) → PHP signals the Java message server → the Java server fans the message out over WebSockets to every connected channel member → background Kafka jobs handle link unfurling, search indexing, and push notifications.

If each person sends 50 messages a day in a workspace of 8,000 members, the message server needs to send at least 8,000 WebSocket pushes per message — and across all of Slack, the message server must handle roughly 1.6 trillion WebSocket updates per day.

That scale is why every design choice — consistent hashing, Flannel edge caching, Vitess sharding — isn't just engineering elegance. It's a hard-won necessity.
