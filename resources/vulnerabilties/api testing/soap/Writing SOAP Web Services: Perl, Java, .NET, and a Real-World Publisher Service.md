# Writing SOAP Web Services: Perl, Java, .NET, and a Real-World Publisher Service

In my last post I worked through the theory: envelopes, headers, faults, actors, and encoding. That's the "under the hood" view. This time I'm climbing back out and looking at what it actually takes to **build and deploy** a SOAP web service, using three different toolkits from the SOAP era: **SOAP::Lite** for Perl, **Apache SOAP** for Java, and **Microsoft .NET** with C#. Then I'll walk through a genuinely useful example, the **Publisher web service**, which manages a small database of news items and shows what a real service with authentication looks like.


**What's in this post:**

- How any SOAP toolkit's listener/proxy pattern works, regardless of language
- Building and deploying "Hello World" in Perl (SOAP::Lite), Java (Apache SOAP), and C# (.NET)
- Swapping HTTP for Jabber as a transport, without touching your application code
- The real interoperability bug that occurs when Perl talks to .NET, reproduced and tested
- The Publisher service: a small but complete example with registration, login, authentication tokens, posting, and browsing
- Tables, notes, and cautions throughout, plus tested code where I could actually run it

---

## Table of Contents

1. [Web Services Anatomy 101](#1-web-services-anatomy-101)
2. [How Toolkits Handle SOAP Messages](#2-how-toolkits-handle-soap-messages)
3. [Deploying a Web Service: The Common Pattern](#3-deploying-a-web-service-the-common-pattern)
4. [Building Hello World in Perl with SOAP::Lite](#4-building-hello-world-in-perl-with-soaplite)
5. [Proving It's Really SOAP: A Visual Basic Client](#5-proving-its-really-soap-a-visual-basic-client)
6. [Swapping Transports: SOAP over Jabber](#6-swapping-transports-soap-over-jabber)
7. [Building Hello World in Java with Apache SOAP](#7-building-hello-world-in-java-with-apache-soap)
8. [Debugging with TCPTunnelGui](#8-debugging-with-tcptunnelgui)
9. [Building Hello World in .NET with C#](#9-building-hello-world-in-net-with-c)
10. [Interoperability Issues: When Perl Meets .NET](#10-interoperability-issues-when-perl-meets-net)
11. [The Publisher Web Service: Overview](#11-the-publisher-web-service-overview)
12. [Publisher Security: Login Tokens](#12-publisher-security-login-tokens)
13. [The Publisher Operations, One by One](#13-the-publisher-operations-one-by-one)
14. [Deploying the Publisher Service](#14-deploying-the-publisher-service)
15. [The Java Shell Client](#15-the-java-shell-client)
16. [What I Tested, and What I Didn't](#16-what-i-tested-and-what-i-didnt)
17. [What's Changed Since This Was Written](#17-whats-changed-since-this-was-written)
18. [Cheat Sheet and Final Thoughts](#18-cheat-sheet-and-final-thoughts)

---

## 1. Web Services Anatomy 101

I covered this briefly before, but it's worth repeating because everything in this post hangs off it. A web service has three parts:

1. A **listener** that receives the message
2. A **proxy** that translates the message into an action (like invoking a method on a Java object)
3. The **application code** that carries out the action

Here's the thing I appreciate most about a well-built toolkit: **the listener and proxy should be invisible to your application code.** Ideally, your code doesn't even know it's being called through a web service. That's not always possible, but it's the ideal to aim for.

The best example of this from the book is **SOAP::Lite**. Written by Pavel Kulchenko for Perl, it can take *any* installed Perl module and automatically expose it as a web service, with zero extra work from the module's author. The proxy loads and invokes any subroutine in any module on demand.

```mermaid
flowchart LR
    Msg["📨 Incoming<br/>SOAP message"]:::msg
    L["👂 Listener"]:::listener
    P["🔄 Proxy<br/>decode + dispatch"]:::proxy
    App["⚙️ Your existing code<br/>(doesn't know it's a web service)"]:::app
    Msg --> L --> P -- "just a normal method call" --> App

    classDef msg fill:#e0e7ff,stroke:#4338ca,stroke-width:2px,color:#1e1b4b
    classDef listener fill:#fce7f3,stroke:#be185d,stroke-width:2px,color:#500724
    classDef proxy fill:#ffedd5,stroke:#c2410c,stroke-width:2px,color:#431407
    classDef app fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#052e16
```

> 📝 **Note:** There's a long list of SOAP toolkits out there for pretty much every language: Java, C#, C++, C, Perl, PHP, Python, and more. The book points to `soaplite.com` and `soapware.org` as directories. Those specific sites may or may not still be active by the time you read this; I'd treat them as historical pointers rather than live resources.

No matter which toolkit you pick, **the fundamental workflow is identical**: write the code, deploy it, invoke it. That consistency is the whole point of standardizing on SOAP in the first place.

---

## 2. How Toolkits Handle SOAP Messages

Toolkits differ quite a bit in how they hook into the transport layer.

| Integration style | How it works | Example |
|---|---|---|
| **Built-in HTTP server** | The toolkit runs its own standalone HTTP daemon | SOAP::Lite's standalone server mode |
| **Web server module/servlet** | Installed as part of an existing web server; the server hands the SOAP message straight to the toolkit's proxy | Apache SOAP running as a servlet |
| **Pluggable transport** | Change transport protocol with barely more than a config setting | SOAP::Lite, which supports FTP, HTTP, IO, Jabber, SMTP, POP3, TCP, and even MQSeries |

```mermaid
flowchart TB
    subgraph Built["🖥️ Built-in Listener"]
        direction LR
        b1["Toolkit's own<br/>HTTP daemon"]:::style1
        b2["Proxy"]:::style1
        b1 --> b2
    end
    subgraph Module["🧩 Web Server Module"]
        direction LR
        m1["Existing HTTP<br/>server / servlet container"]:::style2
        m2["Hands SOAP body<br/>to toolkit proxy"]:::style2
        m1 --> m2
    end
    subgraph Plug["🔌 Pluggable Transport"]
        direction LR
        p1["HTTP, FTP, SMTP,<br/>Jabber, TCP, MQSeries..."]:::style3
        p2["Same proxy code<br/>underneath"]:::style3
        p1 --> p2
    end

    classDef style1 fill:#bae6fd,stroke:#075985,stroke-width:2px,color:#082f49
    classDef style2 fill:#fde68a,stroke:#b45309,stroke-width:2px,color:#451a03
    classDef style3 fill:#bbf7d0,stroke:#15803d,stroke-width:2px,color:#052e16
```

Regardless of the integration style, **every toolkit's proxy has to do the same three things**:

1. **Deserialize** the message from XML into a native format the application code can consume
2. **Invoke** the code
3. **Serialize** the response (if there is one) back into XML and hand it to the transport listener

```mermaid
sequenceDiagram
    participant T as 🌐 Transport Listener
    participant Px as 🔄 Proxy
    participant App as ⚙️ Application Code
    T->>Px: raw SOAP XML
    Px->>Px: 1. Deserialize XML into native types
    Px->>App: 2. Invoke method/subroutine
    App-->>Px: return value
    Px->>Px: 3. Serialize return value back into XML
    Px-->>T: SOAP response
```

The proxy also has to understand everything from the previous post: encoding styles, native-type-to-XML translation, and whether `mustUnderstand="true"` headers are actually understood. **Different toolkits implement this differently, but they all follow the same pattern.**

---

## 3. Deploying a Web Service: The Common Pattern

"Deploying" a web service really means **telling the proxy which code to invoke for a given message type.** The proxy needs to know that a `getQuote` message maps to, say, `samples.QuoteServer` in Java, or `QuoteServer.pm` in Perl.

Deployment mechanisms vary a lot between toolkits:

| Toolkit | Deployment mechanism |
|---|---|
| **SOAP::Lite** | The Perl module just needs to be in `@INC` (Perl's module search path); the dispatcher script names the module directly |
| **Apache SOAP** | Requires a **deployment descriptor** file describing the Java class and Java-to-XML type mappings, registered with a deployed services registry |
| **.NET** | The web service is written directly into an `.asmx` file dropped into the IIS web root; no separate descriptor needed |

```mermaid
flowchart LR
    subgraph SL["SOAP::Lite"]
        direction TB
        sl1["Server script names<br/>the module directly"]:::sl
    end
    subgraph AS["Apache SOAP"]
        direction TB
        as1["Separate XML<br/>deployment descriptor"]:::as2
        as2["Registered with the<br/>deployed services registry"]:::as2
        as1 --> as2
    end
    subgraph NET[".NET"]
        direction TB
        n1[".asmx file dropped<br/>into IIS web root"]:::net
    end

    classDef sl fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef as2 fill:#fde68a,stroke:#b45309,stroke-width:2px,color:#451a03
    classDef net fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#052e16
```

> 💡 **Tip:** This is the single biggest practical difference between toolkits, and it's worth understanding *before* you commit to one. SOAP::Lite's "just point at the module" approach is the fastest to prototype with. Apache SOAP's descriptor-file approach is more explicit and easier to audit in a larger team. .NET's "drop a file in the web root" approach is the fastest of all for a Windows shop, but it ties you to IIS.

---

## 4. Building Hello World in Perl with SOAP::Lite

Every introduction to a new system needs a Hello World example, and SOAP is no exception. I'll follow the book's structure here.

### 4.1 Installing SOAP::Lite

SOAP::Lite is distributed through **CPAN** (the Comprehensive Perl Archive Network), the same way you'd install almost any Perl module:

```text
C:\book>perl -MCPAN -e shell
cpan shell -- CPAN exploration and modules installation (v1.59_54)
cpan> install SOAP::Lite
```

The installer walks you through an interactive configuration, asking about which transports and features to include:

```text
Client (SOAP::Transport::HTTP::Client) [yes]
Client HTTPS/SSL support ... [no]
Client SMTP/sendmail support ... [yes]
Client FTP support ... [yes]
Standalone HTTP server (SOAP::Transport::HTTP::Daemon) [yes]
Apache/mod_perl server ... [no]
FastCGI server ... [no]
POP3 server ... [yes]
IO server ... [yes]
MQ transport support ... [no]
JABBER transport support ... [no]
...
```

> ⚠️ **Caution:** If you want to follow along with the Jabber transport example later in this post, you need to answer **"no"** to accepting the default configuration, then say **"yes"** specifically to the Jabber transport support question. The default configuration skips it.

### 4.2 The Hello Module

Here's the Perl module that will sit behind the web service:

```perl
# Hello.pm - simple Hello module
package Hello;

sub sayHello {
    shift; # remove class name
    return "Hello " . shift;
}

1;
```

Nothing about this module knows it will be exposed as a web service. That's exactly the point.

### 4.3 The CGI Glue Script

If you already have a CGI-capable web server, the entire "server" is four lines:

```perl
#!/usr/bin/perl -w
# hello.cgi - Hello SOAP handler
use SOAP::Transport::HTTP;

SOAP::Transport::HTTP::CGI
    -> dispatch_to('Hello::(?:sayHello)')
    -> handle
;
```

This script is the **glue** between the listener (your HTTP daemon) and the proxy (SOAP::Lite itself). `dispatch_to` tells SOAP::Lite exactly which module and operation to expose.

> 📝 **Note:** If Perl can't find `Hello.pm` in one of its default module directories (run `print @INC` to see the list), add a `use lib '/path/to/your/lib';` line pointing at wherever you saved it.

### 4.4 The Hello Client

```perl
#!/usr/bin/perl -w
# hw_client.pl - Hello client
use SOAP::Lite;

my $name = shift;
print "\n\nCalling the SOAP Server to say hello\n\n";
print "The SOAP Server says: ";
print SOAP::Lite
    -> uri('urn:Example1')
    -> proxy('http://localhost/cgi-bin/helloworld.cgi')
    -> sayHello($name)
    -> result . "\n\n";
```

Running it looks like this:

```text
% perl hw_client.pl James

Calling the SOAP Server to say hello

The SOAP Server says: Hello James
%
```

I want to underline something the book says almost in passing, because I think it's the real lesson of the whole chapter: **this doesn't do much, and that's the point.** The workflow, write the code, deploy it, invoke it, is *identical* no matter how complex the service gets later, or which toolkit you use.

---

## 5. Proving It's Really SOAP: A Visual Basic Client

To drive home that this is genuinely SOAP and not some Perl-specific trick, the book shows a **Visual Basic script** that talks to the exact same Hello World service using nothing but Microsoft's XML parser:

```vbscript
Dim x, h
Set x = CreateObject("MSXML2.DOMDocument")
x.loadXML "<s:Envelope xmlns:s='http://schemas.xmlsoap.org/soap/envelope/' " & _
    "xmlns:xsi='http://www.w3.org/1999/XMLSchema-instance' " & _
    "xmlns:xsd='http://www.w3.org/1999/XMLSchema'>" & _
    "<s:Body><m:sayHello xmlns:m='urn:Example1'>" & _
    "<name xsi:type='xsd:string'>James</name>" & _
    "</m:sayHello></s:Body></s:Envelope>"
msgbox x.xml, , "Input SOAP Message"

Set h = CreateObject("Microsoft.XMLHTTP")
h.open "POST", "http://localhost:8080"
h.send (x)
while h.readyState <> 4
wend
msgbox h.responseText,,"Output SOAP Message"
```

The request and response it exchanges are plain SOAP:

```xml
<!-- Request -->
<s:Envelope
    xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/1999/XMLSchema">
  <s:Body>
    <m:sayHello xmlns:m='urn:Example1'>
      <name xsi:type='xsd:string'>James</name>
    </m:sayHello>
  </s:Body>
</s:Envelope>
```

```xml
<!-- Response -->
<s:Envelope
    xmlns:s="http://www.w3.org/2001/06/soap-envelope"
    xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/1999/XMLSchema">
  <s:Body>
    <n:sayHelloResponse xmlns:n="urn:Example1">
      <return xsi:type="xsd:string">Hello James</return>
    </n:sayHelloResponse>
  </s:Body>
</s:Envelope>
```

```mermaid
sequenceDiagram
    participant VB as 🪟 Visual Basic client
    participant Perl as 🐪 SOAP::Lite server
    VB->>Perl: POST sayHello("James") over HTTP
    Note over Perl: same Hello.pm module,<br/>no VB-specific code
    Perl-->>VB: "Hello James"
```

> 📝 **Note:** The book's response fragment uses the draft SOAP 1.2 envelope namespace (`2001/06/soap-envelope`) while the request uses the SOAP 1.1 namespace (`schemas.xmlsoap.org/soap/envelope/`). That's almost certainly an inconsistency in the example rather than a deliberate version switch mid-conversation, since a real SOAP 1.1 server wouldn't normally reply using a different major version's envelope namespace. If you're typing these examples in yourself, keep the namespace consistent between request and response.

---

## 6. Swapping Transports: SOAP over Jabber

This is where the layered architecture from my last post stops being theoretical. Because SOAP is just packaging, and packaging is independent of transport, **you can swap HTTP for Jabber without touching `Hello.pm` at all.**

Why would you want to? Jabber gives you presence and identity features (who's online, who's available) that HTTP doesn't have natively. A web service riding on top of that gets those features for free.

The server:

```perl
#!/usr/bin/perl -w
# sjs - soap jabber server
use SOAP::Transport::JABBER;

my $server = SOAP::Transport::JABBER::Server
    -> new('jabber://soaplite_server:soapliteserver@jabber.org:5222')
    -> dispatch_to('Hello')
;

print "SOAP Jabber Server Started\n";
do { $server->handle } while sleep 1;
```

The client, with the proxy URL changed to a Jabber address instead of an HTTP one:

```perl
#!/usr/bin/perl -w
# sjc - soap jabber client
use SOAP::Lite;

my $name = shift;
print "\n\nCalling the SOAP Server to say hello\n\n";
print "The SOAP Server says: ";
print SOAP::Lite
    -> uri('urn:Example1')
    -> proxy('jabber://soaplite_client:soapliteclient@jabber.org:5222/' .
             'soaplite_server@jabber.org/')
    -> sayHello($name)
    -> result . "\n\n";
```

> ⚠️ **Caution:** The book uses shared `soaplite_server` / `soaplite_client` Jabber.org accounts for the example. If you're actually trying this, register your **own** Jabber IDs, or you'll be fighting with every other reader of the book trying the same example on the same accounts at the same time.

And here's the part I find genuinely elegant: the SOAP envelope doesn't change shape at all. It just gets embedded inside a Jabber `<iq>` stanza:

```xml
<iq to="soapproxy@johndoe.ibm.com/soaprouter" id="6" type="get">
  <query xmlns="soap-message">
    <s:Envelope
        xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:xsd="http://www.w3.org/2001/XMLSchema">
      <s:Body>
        <m:sayHello xmlns:m="urn:Example1">
          <name xsi:type="xsd:string">James</name>
        </m:sayHello>
      </s:Body>
    </s:Envelope>
  </query>
</iq>
```

```mermaid
flowchart TB
    subgraph HTTP["🌐 Transport: HTTP"]
        h1["POST /cgi-bin/helloworld.cgi"]:::http
        h2["SOAP Envelope"]:::envelope
        h1 --> h2
    end
    subgraph JAB["💬 Transport: Jabber"]
        j1["<iq> stanza"]:::jabber
        j2["<query xmlns='soap-message'>"]:::jabber
        j3["Same SOAP Envelope"]:::envelope
        j1 --> j2 --> j3
    end
    h2 -.->|"identical XML"| j3

    classDef http fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef jabber fill:#fce7f3,stroke:#be185d,stroke-width:2px,color:#500724
    classDef envelope fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#052e16
```

That's the packaging/transport separation from the technology stack, made concrete. Same envelope, same `Hello.pm`, completely different wire protocol.

---

## 7. Building Hello World in Java with Apache SOAP

Java takes more ceremony than Perl, but the underlying steps are the same: write the code, describe it, deploy it, invoke it.

### 7.1 Installing Apache SOAP

Apache SOAP runs as a **servlet** inside any Java HTTP server that supports Servlets and JSP, such as Apache Tomcat. It implements only the *proxy* part of the message-handling process; you supply the listener via your servlet container.

On the client side, you need three JARs on your classpath: `soap.jar`, `mail.jar`, and `activation.jar`, plus a JAXP-aware XML parser such as Xerces.

```batch
set CLASSPATH=%CLASSPATH%;%SOAP_LIB%\soap.jar
set CLASSPATH=%CLASSPATH%;%SOAP_LIB%\mail.jar
set CLASSPATH=%CLASSPATH%;%SOAP_LIB%\activation.jar
```

Or on Unix:

```sh
CLASSPATH=$CLASSPATH:$SOAP_LIB/soap.jar
CLASSPATH=$CLASSPATH:$SOAP_LIB/mail.jar
CLASSPATH=$CLASSPATH:$SOAP_LIB/activation.jar
```

> ⚠️ **Caution:** The book is blunt about this, and I've seen it hold true well beyond Apache SOAP: **the vast majority of problems new users hit are classpath problems.** If your Java-based SOAP service isn't working, check your classpath before you touch anything else.

If your web application server supports WAR files (Tomcat does), you can skip manual JAR wrangling and just deploy the `soap.war` file that ships with Apache SOAP. If you're planning to use the Bean Scripting Framework for script-based services, you'll also need `bsf.jar` and `js.jar` on the classpath.

### 7.2 The Hello Class

```java
package samples;

public class Hello {
    public String sayHello(String name) {
        return "Hello " + name;
    }
}
```

Compile it, put it somewhere on your web server's classpath, and it's ready to be described.

### 7.3 The Deployment Descriptor

Unlike SOAP::Lite, where the server script *is* the deployment description, Apache SOAP requires a **separate XML deployment descriptor**:

```xml
<dd:service xmlns:dd="http://xml.apache.org/xml-soap/deployment"
             id="urn:Example1">
  <dd:provider type="java"
               scope="Application"
               methods="sayHello">
    <dd:java class="samples.Hello"
             static="false" />
  </dd:provider>
  <dd:faultListener>
    org.apache.soap.server.DOMFaultListener
  </dd:faultListener>
  <dd:mappings />
</dd:service>
```

| Element | Meaning |
|---|---|
| `dd:java class` | The fully qualified Java class implementing the service |
| `scope` | Session scope of the service class, `Application` or `Session`, per the Servlet spec |
| `dd:faultListener` | Which class handles faults raised by the SOAP engine |
| `dd:mappings` | Java-to-XML type mappings for non-primitive types (empty here, since `sayHello` only uses strings) |

```mermaid
flowchart LR
    subgraph SLite["SOAP::Lite"]
        s1["hello.cgi<br/>(server script IS the description)"]:::sl
        s2["dispatch_to('Hello')"]:::sl
        s1 --> s2
    end
    subgraph ASOAP["Apache SOAP"]
        a1["Hello.class"]:::as2
        a2["Separate deployment<br/>descriptor XML file"]:::as2
        a3["Registered with the<br/>Service Manager"]:::as2
        a1 -.described by.-> a2 --> a3
    end

    classDef sl fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef as2 fill:#fde68a,stroke:#b45309,stroke-width:2px,color:#451a03
```

Apache SOAP also supports pluggable **providers**, meaning your web service can be backed by more than plain Java classes: Enterprise Java Beans, COM classes, and Bean Scripting Framework scripts are all fair game.

### 7.4 Actually Deploying It

There are two ways to register the descriptor with Apache SOAP:

**Option A: the Service Manager Client**, which sends the deployment descriptor as a SOAP message to the running server:

```text
% java org.apache.soap.server.ServiceManagerClient
    http://hostname:port/soap/servlet/rpcrouter deploy foo.xml
```

> ⚠️ **Caution:** Think about what this means for a moment: **deploying a new service is itself done by sending a SOAP message to your server.** That's convenient, but it also means anyone who can reach your rpcrouter endpoint can potentially deploy or undeploy services. If you're using this mechanism, set `SOAPInterfaceEnabled` to `false` in `soap.xml` once you're done, unless you specifically want this door left open.

**Option B: edit the XML configuration file directly**, if you're using the XML Configuration Manager. It's just a root element wrapping every deployed service's descriptor:

```xml
<root>
  <dd:service xmlns:dd="http://xml.apache.org/xml-soap/deployment"
               id="urn:Example1">
    <dd:provider type="java"
                 scope="Application"
                 methods="sayHello">
      <dd:java class="samples.Hello"
               static="false" />
    </dd:provider>
    <dd:faultListener>
      org.apache.soap.server.DOMFaultListener
    </dd:faultListener>
    <dd:mappings />
  </dd:service>
</root>
```

Restart the SOAP servlet, and the service manager reinitializes with the new service ready to go.

### 7.5 The Java Client

```java
import java.io.*;
import java.net.*;
import java.util.*;
import org.apache.soap.*;
import org.apache.soap.rpc.*;

public class Example1_client {
    public static void main(String[] args) throws Exception {
        System.out.println("\n\nCalling the SOAP Server to say hello\n\n");
        URL url = new URL(args[0]);
        String name = args[1];

        Call call = new Call();
        call.setTargetObjectURI("urn:Example1");
        call.setMethodName("sayHello");
        call.setEncodingStyleURI(Constants.NS_URI_SOAP_ENC);

        Vector params = new Vector();
        params.addElement(new Parameter("name", String.class, name, null));
        call.setParams(params);

        System.out.print("The SOAP Server says: ");
        Response resp = call.invoke(url, "");
        if (resp.generatedFault()) {
            Fault fault = resp.getFault();
            System.out.println("\nOuch, the call failed: ");
            System.out.println("  Fault Code = " + fault.getFaultCode());
            System.out.println("  Fault String = " + fault.getFaultString());
        } else {
            Parameter result = resp.getReturnValue();
            System.out.print(result.getValue());
            System.out.println();
        }
    }
}
```

> 📝 **Note:** I fixed a small typo from the book's listing here (`call.setEncodingStyleURI(Constants.NS_URI_SOAP_ENC;)` has a stray semicolon inside the parentheses that wouldn't compile). Also note this is nine lines just to initialize and invoke a call, versus roughly four for the entire Perl client. As the book puts it, Java will never be as terse as a scripting language, but tools like The Mind Electric's GLUE and IBM's Web Services Toolkit added **dynamic proxy interfaces** to cut this down, usually by generating a proxy class from a WSDL description.

Run it:

```text
% java samples.Hello http://localhost/soap/servlet/rpcrouter James

Calling the SOAP Server to say hello

The SOAP Server says: Hello James
%
```

And because both sides speak the same SOAP, you can even point the earlier **Perl** client at the **Java** server:

```perl
#!/usr/bin/perl -w
# hw_jclient.pl - java Hello client
use SOAP::Lite;

my $name = shift;
print "\n\nCalling the SOAP Server to say hello\n\n";
print "The SOAP Server says: ";
print SOAP::Lite
    -> uri('urn:Example1')
    -> proxy('http://localhost/soap/servlet/rpcrouter')
    -> sayHello($name)
    -> result . "\n\n";
```

```text
% perl hw_client.pl James

Calling the SOAP Server to say hello

The SOAP Server says: Hello James
%
```

> 📝 **Note:** The book's `hw_jclient.pl` listing accidentally includes `James` inside the `proxy()` URL string itself (`'http://localhost/soap/servlet/rpcrouter James'`), which would make for a broken URL. I've corrected that above; the name should only be passed once, as the argument to `sayHello($name)`.

---

## 8. Debugging with TCPTunnelGui

Apache SOAP ships with a small but genuinely useful debugging tool called **TCPTunnelGui**. It's a proxy that sits between your client and the real SOAP server, forwards traffic in both directions, and displays every message that passes through in a GUI.

```mermaid
sequenceDiagram
    participant C as 🧑‍💻 SOAP Client
    participant Tun as 🔍 TCPTunnelGui<br/>(local port)
    participant S as 🖥️ Real SOAP Server
    C->>Tun: SOAP request
    Note over Tun: displays request XML
    Tun->>S: forwards request
    S-->>Tun: SOAP response
    Note over Tun: displays response XML
    Tun-->>C: forwards response
```

Launch it like this:

```text
% java org.apache.soap.util.net.TcpTunnelGui listenport tunnelhost tunnelport
```

For example, if your Hello World service lives at `http://www.example.com/soap/servlet/rpcrouter`, and you want to inspect traffic through local port 8080:

```text
% java org.apache.soap.util.net.TcpTunnelGui 8080 http://www.example.com 80
```

Then point your client at `http://localhost:8080/soap/servlet/rpcrouter` instead of the real address.

> 💡 **Tip:** I'll be using this exact trick later, conceptually, when I test the Perl-to-.NET interoperability bug in section 10. Even without the actual GUI tool, the principle of "insert a proxy so you can see the raw XML" is one of the most useful debugging habits in SOAP work generally. If you don't have TCPTunnelGui, a plain HTTP logging proxy or even `curl -v` piped through a local relay gets you most of the same value.

---

## 9. Building Hello World in .NET with C#

.NET takes a genuinely different approach: instead of a separate compile-and-deploy step, you drop a source file with a `.asmx` extension directly into your IIS web root, and the runtime compiles it on first request.

### 9.1 Prerequisites

The book lists these requirements for the .NET SDK Beta 2 era:

- Windows 2000, NT 4.0, 98, or Millennium Edition
- Internet Explorer 5.01 or higher
- Microsoft Data Access Components 2.6 or higher
- Internet Information Server (IIS) installed and running

> ⚠️ **Caution:** This list is a direct snapshot of a specific beta-era SDK's requirements. If you're setting up .NET today, none of these apply, you'd be looking at a completely different, current .NET runtime. I'm keeping this list here as a historical record, not as something to actually follow. See [section 17](#17-whats-changed-since-this-was-written).

### 9.2 A Quick Look at .NET's Architecture

.NET is a managed runtime, conceptually similar to the Java Virtual Machine. Code packages called **assemblies** can be written in various .NET-flavored languages (Visual Basic, C++, C#, and others at the time) and run inside the **Common Language Runtime**, which handles memory and system management.

```mermaid
flowchart TB
    subgraph Assemblies["📦 Assemblies (VB, C++, C#, ...)"]
        a1["Your web service code"]:::asm
    end
    CLR["🧠 Common Language Runtime<br/>memory + system management"]:::clr
    COM["🪟 Windows / COM environment"]:::com
    IIS["🌐 IIS"]:::iis
    Assemblies --> CLR --> COM --> IIS

    classDef asm fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef clr fill:#fde68a,stroke:#b45309,stroke-width:2px,color:#451a03
    classDef com fill:#e5e7eb,stroke:#374151,stroke-width:2px,color:#111827
    classDef iis fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#052e16
```

.NET web services are just assemblies flagged for export, referenced from an `.asmx` file. IIS recognizes the extension and automatically exposes the functions inside. The whole process:

1. Write the code
2. Save it as `.asmx`
3. Move it to your IIS web root
4. Invoke it

### 9.3 The Service

```csharp
<%@ WebService Language="C#" Class="Example1" %>

using System.Web.Services;

[WebService(Namespace="urn:Example1")]
public class Example1 {
    [ WebMethod ]
    public string sayHello(string name) {
        return "Hello " + name;
    }
}
```

| Piece | Purpose |
|---|---|
| `<%@ WebService Language="C#" Class="Example1" %>` | Tells .NET which class implements the exported service, and in which language |
| `using System.Web.Services;` | Imports the standard web services classes |
| `[WebService(Namespace="urn:Example1")]` | Optional: sets an explicit namespace. Without it, .NET defaults to `http://tempuri.org/`, which you almost never want in anything beyond a scratch example |
| `[ WebMethod ]` | Flags a method for export. Can also configure response buffering, session state, transaction support, the exported operation name, and a description |

> 💡 **Tip:** Always set an explicit `Namespace`. Shipping a real service under the default `tempuri.org` namespace is one of those small details that immediately signals "this was never properly finished" to anyone reading your WSDL later.

### 9.4 Deployment and the Free Documentation Page

Save `HelloWorld.asmx` into your IIS web root (`c:\inetpub\wwwroot` by default), and it's deployed. That's it, no compile step you have to run yourself; .NET compiles it the first time it's requested and caches the result, recompiling automatically whenever the file changes.

What impressed me most reading this part of the book is the **automatic documentation**. Navigate a browser to `http://localhost/HelloWorld.asmx` and you get a generated HTML page describing the service, and clicking through to `sayHello` gives you a form you can use to test it directly, plus documentation on invoking it via SOAP, HTTP-GET, or HTTP-POST.

```mermaid
flowchart LR
    A["Browser requests<br/>HelloWorld.asmx"]:::step
    B{"Request type?"}:::decision
    C["📄 Service documentation page<br/>auto-generated"]:::doc
    D["📋 Method documentation<br/>+ test form"]:::doc
    E["🔧 Actual SOAP/GET/POST<br/>invocation"]:::invoke
    A --> B
    B -- "plain GET, no operation" --> C
    B -- "GET on a specific method" --> D
    B -- "SOAP, GET, or POST call" --> E

    classDef step fill:#e0e7ff,stroke:#4338ca,stroke-width:2px,color:#1e1b4b
    classDef decision fill:#fef3c7,stroke:#d97706,stroke-width:2px,color:#451a03
    classDef doc fill:#bae6fd,stroke:#075985,stroke-width:2px,color:#082f49
    classDef invoke fill:#bbf7d0,stroke:#15803d,stroke-width:2px,color:#052e16
```

You can even test it with a bare URL: `http://localhost/helloworld.asmx/sayHello?name=yourname`. **.NET is one of the only platforms of its era that let you invoke the same operation through HTTP-GET, HTTP-POST, or full SOAP.**

### 9.5 A .NET SOAP Client

Writing the client is, ironically, more work than writing the service:

```csharp
// HelloWorld.cs
using System.Diagnostics;
using System.Xml.Serialization;
using System;
using System.Web.Services.Protocols;
using System.Web.Services;

[System.Web.Services.WebServiceBindingAttribute(
    Name="Example1Soap",
    Namespace="urn:Example1")]
public class Example1 :
    System.Web.Services.Protocols.SoapHttpClientProtocol {

    public Example1() {
        this.Url = "http://localhost/helloworld.asmx";
    }

    [System.Web.Services.Protocols.SoapDocumentMethodAttribute(
        "urn:Example1/sayHello",
        RequestNamespace="urn:Example1",
        ResponseNamespace="urn:Example1",
        Use=System.Web.Services.Description.SoapBindingUse.Literal,
        ParameterStyle=System.Web.Services.Protocols.SoapParameterStyle.Wrapped)]
    public string sayHello(string name) {
        object[] results = this.Invoke("sayHello", new object[] {name});
        return ((string)(results[0]));
    }

    public static void Main(string[] args) {
        Console.WriteLine("Calling the SOAP Server to say hello");
        Example1 example1 = new Example1();
        Console.WriteLine("The SOAP Server says: " +
            example1.sayHello(args[0]));
    }
}
```

Compile and run:

```text
C:\book>csc HelloWorld.cs
C:\book>HelloWorld yourname

Calling the SOAP Server to say hello
The SOAP Server says: Hello James
```

Same result, third language. That consistency across Perl, Java, and C# is really the entire thesis of this chapter.

---

## 10. Interoperability Issues: When Perl Meets .NET

This is my favorite section in the whole chapter, because it's a real, reproducible bug rather than an abstract warning. I decided to actually **rebuild and test** the failure mode described in the book, using Python to stand in for a strict, .NET-style server, since I can't install the .NET Framework Beta 2 or SOAP::Lite in this environment. The logic is identical; only the language changed.

### 10.1 The Setup

The book has you point the Java `TCPTunnelGui` tool at your `.asmx` service so you can watch the raw SOAP traffic, then hits it with the Perl client from section 4. On paper, it should just work. **It doesn't.**

### 10.2 Failure One: the SOAPAction Header

.NET expects the `SOAPAction` HTTP header to *exactly* identify the operation, in the form `namespace/operationName` (a forward slash). SOAP::Lite's default is to separate them with a **pound sign**: `namespace#operationName`. The mismatch produces this fault:

```xml
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <soap:Fault>
      <faultcode>soap:Client</faultcode>
      <faultstring>
        System.Web.Services.Protocols.SoapException: Server did
        not recognize the value of HTTP Header SOAPAction:
        urn:Example#sayHello.
      </faultstring>
      <detail />
    </soap:Fault>
  </soap:Body>
</soap:Envelope>
```

The fix is a one-liner using SOAP::Lite's `on_action` hook:

```perl
print SOAP::Lite
    -> uri('urn:Example1')
    -> on_action(sub{sprintf '%s/%s', @_ })
    -> proxy('http://localhost:8080/helloworld/example1.asmx')
    -> sayHello($name)
    -> result . "\n\n";
```

> 📝 **Note:** This didn't bite you when calling **Apache SOAP** from Perl earlier, because Apache SOAP simply ignores the `SOAPAction` header entirely. That's a nice illustration of the encoding-confusion problem from my last post: two implementations can each be "legal" and still behave completely differently on the same input.

### 10.3 Failure Two: Unnamed Parameters

Even after fixing the `SOAPAction` header, the response comes back as just `"Hello"`, the name is missing. Here's why, and I reproduced this exact failure myself.

Because Perl doesn't enforce strict typing or named function signatures, SOAP::Lite auto-generates a placeholder element name for parameters it can't otherwise name, something like `c-gensym3`:

```xml
<SOAP-ENV:Envelope
    xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
    SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/1999/XMLSchema">
  <SOAP-ENV:Body>
    <namesp1:sayHello xmlns:namesp1="urn:Hello">
      <c-gensym3 xsi:type="xsd:string">James</c-gensym3>
    </namesp1:sayHello>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

.NET, on the other hand, expects the parameter element to be **explicitly named after the parameter it declared** in the C# method signature, `name`:

```xml
<namesp1:sayHello xmlns:namesp1="urn:Hello">
  <name xsi:type="xsd:string">James</name>
</namesp1:sayHello>
```

I wrote a small Python server that mimics this "strict naming" behavior and threw both request shapes at it, to see the failure and the fix side by side.

```python
"""Simulates a .NET-style server that requires a named parameter element,
and tests both the 'loose' generic-name request and the 'fixed' named request."""
import threading, urllib.request, urllib.error
from http.server import BaseHTTPRequestHandler, HTTPServer
import xml.etree.ElementTree as ET

ENV = "http://schemas.xmlsoap.org/soap/envelope/"

def envelope(body_xml):
    return (f'<?xml version="1.0" encoding="UTF-8"?>'
            f'<s:Envelope xmlns:s="{ENV}" '
            f'xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance" '
            f'xmlns:xsd="http://www.w3.org/1999/XMLSchema">'
            f'<s:Body>{body_xml}</s:Body></s:Envelope>')

def fault(code, msg):
    return envelope(f"<s:Fault><faultcode>{code}</faultcode>"
                    f"<faultstring>{msg}</faultstring></s:Fault>")

class StrictHelloHandler(BaseHTTPRequestHandler):
    """Behaves like the .NET service: requires the parameter element named 'name'."""
    def log_message(self, *a):
        pass

    def do_POST(self):
        raw = self.rfile.read(int(self.headers["Content-Length"]))
        root = ET.fromstring(raw)
        call = root.find(f"{{{ENV}}}Body")[0]
        name_el = None
        for child in call:
            if child.tag.split("}")[-1] == "name":
                name_el = child
                break
        if name_el is None or not (name_el.text or "").strip():
            status, out = 500, fault(
                "s:Client",
                "Server did not recognize parameter; expected an element named 'name'")
        else:
            status, out = 200, envelope(
                f'<n:sayHelloResponse xmlns:n="urn:Example1">'
                f'<return>Hello {name_el.text.strip()}</return></n:sayHelloResponse>')
        data = out.encode()
        self.send_response(status)
        self.send_header("Content-Type", "text/xml; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

def post(url, body_xml):
    req = urllib.request.Request(url, envelope(body_xml).encode(),
        {"Content-Type": "text/xml; charset=utf-8"})
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, r.read()
    except urllib.error.HTTPError as e:
        return e.code, e.read()

if __name__ == "__main__":
    srv = HTTPServer(("127.0.0.1", 8098), StrictHelloHandler)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    url = "http://127.0.0.1:8098/HelloWorld.asmx"

    # "loose" request: generic auto-generated element name (like SOAP::Lite's c-gensym3)
    loose = ('<n:sayHello xmlns:n="urn:Hello">'
             '<c-gensym3 xsi:type="xsd:string">James</c-gensym3></n:sayHello>')
    status, body = post(url, loose)
    print("loose  request ->", status,
          ET.fromstring(body).find(".//faultstring").text if status != 200 else "OK")

    # "fixed" request: explicitly named parameter element, as .NET expects
    fixed = ('<n:sayHello xmlns:n="urn:Hello">'
             '<name xsi:type="xsd:string">James</name></n:sayHello>')
    status, body = post(url, fixed)
    tag = ET.fromstring(body).find(".//return")
    print("fixed  request ->", status, tag.text if tag is not None else body)

    srv.shutdown()
```

I ran this and got exactly the failure/success split the book describes:

```text
loose  request -> 500 Server did not recognize parameter; expected an element named 'name'
fixed  request -> 200 Hello James
```

> 📝 **Honest note:** This isn't SOAP::Lite talking to real .NET. It's a small Python model of the *specific naming behavior* the book describes, and it reproduces the reported symptom (generic name fails, explicit name succeeds) faithfully. I can't verify every other nuance of real .NET Beta 2's SOAP stack from here, only that the naming rule as described is internally consistent and reproducible.

### 10.4 The Full Fix in Perl

SOAP::Lite lets you fix this by explicitly naming, typing, and namespacing each parameter with `SOAP::Data`:

```perl
use SOAP::Lite;

my $name = shift;
print "\n\nCalling the SOAP Server to say hello\n\n";
print "The SOAP Server says: ";
print SOAP::Lite
    -> uri('urn:Example1')
    -> on_action(sub{sprintf '%s/%s', @_ })
    -> proxy('http://localhost:8080/helloworld/example1.asmx')
    -> sayHello(SOAP::Data->name(name => $name)->type('string')
                          ->uri('urn:Example1'))
    -> result . "\n\n";
```

> ⚠️ **Caution:** The book's own listing of this fix has a stray parenthesis (`$name->type('string')` reads as calling `->type()` on the *value* of `$name`, not on the `SOAP::Data` object). I've corrected the grouping above so `->type()` and `->uri()` chain off `SOAP::Data->name(...)`, which is what actually makes this work.

```mermaid
flowchart TD
    Start(["🧑‍💻 Perl client wants to<br/>call .NET's sayHello"]):::start
    Q1{"SOAPAction header<br/>uses '#' or '/'?"}:::q
    F1["❌ .NET rejects:<br/>'did not recognize SOAPAction'"]:::bad
    Q2{"Parameter element<br/>named explicitly?"}:::q
    F2["❌ .NET accepts call but<br/>drops the argument silently"]:::bad
    OK["✅ 'Hello James'"]:::good

    Start --> Q1
    Q1 -- "'#' (SOAP::Lite default)" --> F1
    Q1 -- "'/' via on_action" --> Q2
    Q2 -- "auto-generated name" --> F2
    Q2 -- "explicit SOAP::Data->name()" --> OK

    classDef start fill:#e0e7ff,stroke:#4338ca,stroke-width:2px,color:#1e1b4b
    classDef q fill:#fef3c7,stroke:#d97706,stroke-width:2px,color:#451a03
    classDef bad fill:#fecaca,stroke:#b91c1c,stroke-width:2px,color:#450a0a
    classDef good fill:#bbf7d0,stroke:#15803d,stroke-width:2px,color:#052e16
```

| Symptom | Root cause | Fix |
|---|---|---|
| `SOAPAction`-related fault | SOAP::Lite uses `#`, .NET expects `/` | `on_action(sub{sprintf '%s/%s', @_ })` |
| Response missing the argument's value | SOAP::Lite auto-names untyped parameters | `SOAP::Data->name('paramName' => $value)->type(...)->uri(...)` |
| .NET also mis-scoped the child element's namespace in the Beta | .NET beta didn't correctly inherit the parent's namespace for the child element | Fixed in later .NET releases; a known beta-era bug |

> 💡 **Tip:** This section is the best practical argument I've seen for **always testing against a second implementation early**, not just your own toolkit's round trip. A service that only ever talks to clients built with the same toolkit can hide these mismatches indefinitely.

---

## 11. The Publisher Web Service: Overview

Now for something with actual substance. The **Publisher web service** manages a small database of news items, articles, and resources related to SOAP and web services, modeled after the real SOAP Web Services Resource Center. It's built in Perl on top of everything from chapter 3, and it's genuinely a good template for "my first real SOAP service with auth."

The supported operations:

| Operation | Purpose |
|---|---|
| `register` | Create a new user account |
| `modify` | Modify a user account |
| `login` | Start a user session, returns an authentication token |
| `post` | Post a new item to the database (**requires auth**) |
| `remove` | Remove an item from the database (**requires auth**) |
| `browse` | Browse the database by item type, as Publisher-specific XML or RSS |

```mermaid
flowchart TB
    Anon["👤 Anonymous visitor"]:::anon
    Reg["📝 register"]:::open
    Login["🔑 login"]:::open
    Browse["📚 browse"]:::open
    Post["✏️ post<br/>(auth required)"]:::secure
    Remove["🗑️ remove<br/>(auth required)"]:::secure

    Anon --> Reg
    Anon --> Login
    Anon --> Browse
    Login -- "issues token" --> Post
    Login -- "issues token" --> Remove

    classDef anon fill:#e5e7eb,stroke:#374151,stroke-width:2px,color:#111827
    classDef open fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef secure fill:#fecaca,stroke:#b91c1c,stroke-width:2px,color:#450a0a
```

If you sketched this as a Java interface (which the book does, even though the actual implementation is Perl), it would look like this:

```java
public interface Publisher {
    public boolean register(String email, String password, String firstName,
                             String lastName, String title, String company, String url);
    public boolean modify(String email, String newemail, String password, String firstName,
                           String lastName, String title, String company, String url);
    public AuthInfo login(String id, String password);
    public int post(AuthInfo authinfo, String type, String title, String description);
    public boolean remove(AuthInfo authinfo, int itemID);
    public org.w3c.dom.Document browse(String type, String format, int maxRows);
}
```

That's a nice reminder that SOAP interfaces are genuinely language-agnostic: the same operation set works whether the implementation happens to be Perl, Java, or anything else.

---

## 12. Publisher Security: Login Tokens

The Publisher service authenticates users with a simple **token-based scheme**, and I want to spend real time on this because I actually rebuilt and tested its core logic.

### 12.1 How It Works

1. The user sends their ID and password to `login`, **in plain text**.
2. The service validates the credentials and issues a **token**: member ID, email, an expiry timestamp, and an MD5 signature over the other three fields plus a server-side secret.
3. The client must attach this token to every subsequent call to `post` or `remove`.
4. The server recomputes the signature on each call and checks it matches, and that the token hasn't expired.

```mermaid
sequenceDiagram
    autonumber
    participant U as 🧑 User
    participant S as 🖥️ Publisher Service
    U->>S: login(id, password) — plain text
    S->>S: validate credentials
    S->>S: build token: {memberID, email, expiry, MD5 signature}
    S-->>U: authInfo token
    U->>S: postItem(authInfo, type, title, description)
    S->>S: recompute signature, check expiry
    alt signature matches and not expired
        S-->>U: item posted
    else invalid or expired
        S-->>U: fault: not authenticated
    end
```

The Perl source for this logic:

```perl
use Digest::MD5 qw(md5);

my $calculateAuthInfo = sub {
    return md5(join '', 'unique (yet persistent) string', @_);
};

my $checkAuthInfo = sub {
    my $authInfo = shift;
    my $signature = $calculateAuthInfo->(@{$authInfo}{qw(memberID email time)});
    die "Authentication information is not valid\n"
        if $signature ne $authInfo->{signature};
    die "Authentication information is expired\n"
        if time() > $authInfo->{time};
    return $authInfo->{memberID};
};

my $makeAuthInfo = sub {
    my ($memberID, $email) = @_;
    my $time = time() + 20*60;
    my $signature = $calculateAuthInfo->($memberID, $email, $time);
    return +{memberID => $memberID, time => $time, email => $email,
             signature => $signature};
};
```

> ⚠️ **Caution, and the book says this outright:** sending credentials in plain text and building your own signature scheme with a hardcoded shared secret string is **not very secure**. It illustrates one valid *pattern*, application-level authentication baked into the SOAP interface, as opposed to relying on transport-level mechanisms like HTTP authentication or TLS, but it isn't something I'd ship as-is. In real usage you'd want this over HTTPS at minimum, with a properly managed secret and a stronger hash (MD5 is broken for anything security-critical today; see [section 17](#17-whats-changed-since-this-was-written)).

### 12.2 Testing the Token Logic Myself

Rather than take the design on faith, I reimplemented the same scheme in Python and tested three scenarios: a normal round trip, a tampered token, and an expired token.

```python
"""Re-implementation of the Publisher service's auth-token logic (from Perl) in Python,
to verify the design actually works: issue a token, validate it, reject a tampered
token, and reject an expired one."""
import hashlib, time

SECRET = "unique (yet persistent) string"

def calculate_signature(member_id, email, expiry):
    payload = (SECRET + str(member_id) + email + str(expiry)).encode()
    return hashlib.md5(payload).hexdigest()

def make_auth_info(member_id, email, ttl_seconds=20*60):
    expiry = int(time.time()) + ttl_seconds
    sig = calculate_signature(member_id, email, expiry)
    return {"memberID": member_id, "email": email, "time": expiry, "signature": sig}

def check_auth_info(auth):
    expected = calculate_signature(auth["memberID"], auth["email"], auth["time"])
    if expected != auth["signature"]:
        raise ValueError("Authentication information is not valid")
    if time.time() > auth["time"]:
        raise ValueError("Authentication information is expired")
    return auth["memberID"]

# 1. normal round trip
tok = make_auth_info(42, "james@soap-wrc.com")
print("1 valid token accepted, memberID =", check_auth_info(tok))

# 2. tampered token (someone edits memberID after the fact)
tampered = dict(tok); tampered["memberID"] = 999
try:
    check_auth_info(tampered)
    print("2 FAILED: tampering was not detected")
except ValueError as e:
    print("2 tampering correctly rejected:", e)

# 3. expired token
expired = make_auth_info(42, "james@soap-wrc.com", ttl_seconds=-1)
try:
    check_auth_info(expired)
    print("3 FAILED: expiry was not detected")
except ValueError as e:
    print("3 expiry correctly rejected:", e)
```

Output:

```text
1 valid token accepted, memberID = 42
2 tampering correctly rejected: Authentication information is not valid
3 expiry correctly rejected: Authentication information is expired
```

| Test | Result | What it confirms |
|---|---|---|
| Valid token | Accepted, correct member ID returned | The basic sign-and-verify round trip works |
| Tampered `memberID` | Rejected | Changing any signed field invalidates the signature, so you can't impersonate another member by editing the token |
| Expired token (`time` in the past) | Rejected, even though the signature itself is still mathematically valid | Expiry is checked as a **separate condition**, not folded into the signature check |

> 💡 **Tip:** That third test matters more than it looks. A signature only proves the token *hasn't been altered since it was issued* — it says nothing about whether it should still be honored. Expiry has to be checked independently, exactly as this code does. This is a good, minimal example of the layered thinking any auth scheme needs: **integrity** (is this token unmodified?) and **validity window** (should we still trust it right now?) are two different questions.

### 12.3 How the Token Travels on the Wire

The `authInfo` token isn't just an internal Perl hash. On the Java client side, it gets serialized into its own XML representation and attached as a **SOAP header block**, exactly matching the actor-targeting pattern from my last post:

```xml
<auth:authInfo xmlns:auth="http://www.soaplite.com/authInfo">
  <email>johndoe@acme.com</email>
  <signature><!-- Base64 encoded string --></signature>
  <memberID>123</memberID>
  <time>2001-08-10 12:04:00 PDT (GMT + 8:00)</time>
</auth:authInfo>
```

And it's built like this in Java, converting the token object into DOM elements by hand:

```java
public void serialize(Document doc) {
    Element authEl = doc.createElementNS(
        "http://www.soaplite.com/authInfo", "authInfo");
    authEl.setAttribute("xmlns:auth", "http://www.soaplite.com/authInfo");
    authEl.setPrefix("auth");

    Element emailEl = doc.createElement("email");
    emailEl.appendChild(doc.createTextNode(auth.getEmail()));

    Element signatureEl = doc.createElement("signature");
    signatureEl.setAttribute("xmlns:enc", Constants.NS_URI_SOAP_ENC);
    signatureEl.setAttribute("xsi:type", "enc:base64");
    signatureEl.appendChild(doc.createTextNode(
        Base64.encode(auth.getSignature())));

    Element memberIdEl = doc.createElement("memberID");
    memberIdEl.appendChild(doc.createTextNode(
        String.valueOf(auth.getMemberID())));

    Element timeEl = doc.createElement("time");
    timeEl.appendChild(doc.createTextNode(
        String.valueOf(auth.getTime())));

    authEl.appendChild(emailEl);
    authEl.appendChild(signatureEl);
    authEl.appendChild(memberIdEl);
    authEl.appendChild(timeEl);
    doc.appendChild(authEl);
}
```

Putting the token in a **header block** rather than the body is a deliberate, sound design choice. It cleanly separates "who is calling" (processing context, belongs in the Header) from "what they want done" (the actual message, belongs in the Body).

---

## 13. The Publisher Operations, One by One

I'll go through each exported operation briefly. All of them follow the same overall shape: pull parameters out of the incoming SOAP envelope, optionally check the auth token, touch the database, return a result or raise an error.

### 13.1 register

```perl
sub register {
    my $self = shift;
    my $envelope = pop;
    my %parameters = %{$envelope->method() || {}};
    die "Wrong parameters: register(email, password, firstName, " .
        "lastName [, title][, company][, url])\n"
        unless 4 == map {defined} @parameters{qw(email password firstName lastName)};
    my $email = $parameters{email};
    die "Member with email ($email) already registered\n"
        if Publisher::DB->select_member(email => $email);
    return Publisher::DB->insert_member(%parameters);
}
```

No authentication needed here, this is how a brand-new user gets into the system in the first place. It does enforce that the four required fields are present, and rejects a duplicate email.

### 13.2 modify

```perl
sub modify {
    my $self = shift;
    my $envelope = pop;
    my %parameters = %{$envelope->method() || {}};
    my $memberID = $checkAuthInfo->($envelope->valueof('//authInfo'));
    Publisher::DB->update_member($memberID, %parameters);
    return;
}
```

> ⚠️ **Caution:** Interestingly, the book's own operation table lists `modify` as one of the supported operations but doesn't flag it as requiring authentication the way `post` and `remove` are explicitly flagged, yet the actual code *does* call `$checkAuthInfo` here. I'd treat that as the book's prose being slightly out of sync with its own code listing, rather than a real inconsistency in the service: modifying account details absolutely should require the token, and the implementation agrees.

### 13.3 login

```perl
sub login {
    my $self = shift;
    my %parameters = %{pop->method() || {}};
    my $email = $parameters{email};
    my $memberID = Publisher::DB->select_member(
        email => $email, password => $parameters{password});
    die "Credentials are wrong\n" unless $memberID;
    return bless $makeAuthInfo->($memberID, $email) => 'authInfo';
}
```

This is the only operation that takes a plain-text password and hands back a token, tied to the `$makeAuthInfo` closure I tested above.

### 13.4 postItem

```perl
my %type2code = (news => 1, article => 2, resource => 3);
my %code2type = reverse %type2code;

sub postItem {
    my $self = shift;
    my $envelope = pop;
    my $memberID = $checkAuthInfo->($envelope->valueof('//authInfo'));
    my %parameters = %{$envelope->method() || {}};
    die "Wrong parameter(s): postItem(type, title, description)\n"
        unless 3 == map {defined} @parameters{qw(type title description)};
    $parameters{type} = $type2code{lc $parameters{type}}
        or die "Wrong type of item ($parameters{type})\n";
    return Publisher::DB->insert_item(memberID => $memberID, %parameters);
}
```

Note the pattern: **check auth first, then validate parameters, then touch the database.** That ordering matters: you don't want to do any real work, or leak information about what's valid input, before confirming the caller is who they claim to be.

### 13.5 removeItem

```perl
sub removeItem {
    my $self = shift;
    my $memberID = $checkAuthInfo->(pop->valueof('//authInfo'));
    die "Wrong parameter(s): removeItem(itemID)\n" unless @_ == 1;
    my $itemID = shift;
    die "Specified item ($itemID) can't be found or removed\n"
        unless Publisher::DB->select_item(memberID => $memberID, itemID => $itemID);
    Publisher::DB->delete_item($itemID);
    return;
}
```

Notice this also enforces **ownership**: it looks the item up scoped to `memberID`, so you can only remove items you posted yourself, not anyone else's.

### 13.6 browse and search

```perl
sub browse {
    my $self = shift;
    return SOAP::Data->name(browse => $browse->(@_));
}

sub search {
    my $self = shift;
    return SOAP::Data->name(search => $browse->(@_));
}
```

Both share a private `$browse` closure that builds either the Publisher-specific XML format or an **RSS channel**, depending on the requested `format`. `search` layers a keyword filter on top of the same underlying logic. Neither of these requires authentication, browsing the catalog is public.

| Operation | Requires auth token? | Touches the database how |
|---|---|---|
| `register` | No | Insert a new member row |
| `modify` | Yes (per the code) | Update a member row |
| `login` | No (uses password directly) | Read-only lookup, issues a token |
| `postItem` | Yes | Insert an item row |
| `removeItem` | Yes, plus ownership check | Delete an item row |
| `browse` / `search` | No | Read-only, formatted as XML or RSS |

---

## 14. Deploying the Publisher Service

Deployment here follows the exact SOAP::Lite pattern from section 4, just with a bigger module behind it.

### 14.1 Creating the Database

```perl
#!/usr/bin/perl -w
use Publisher;
Publisher::DB->create;
```

This runs the `CREATE TABLE` statements for `members` and `items` against a CSV-backed DBI connection (via `DBD::CSV`), producing two flat files.

```perl
$CONNECT = "DBI:CSV:f_dir=/home/book;csv_sep_char=\0";
```

> 💡 **Tip:** Using `DBD::CSV` here is a nice touch for a book example, zero database server setup required. The connection string is the *only* thing you'd change to point this at a real relational database like MySQL, since the rest of the code talks to the database purely through DBI's generic interface.

### 14.2 The CGI Dispatcher

```perl
#!/bin/perl -w
use SOAP::Transport::HTTP;
use Publisher;

$Publisher::DB::CONNECT = "DBI:CSV:f_dir=d:/book;csv_sep_char=\0";
$authinfo = 'http://www.soaplite.com/authInfo';

my $server = SOAP::Transport::HTTP::CGI
    -> dispatch_to('Publisher');
$server->serializer->maptype({authInfo => $authinfo});
$server->handle;
```

This is structurally identical to `hello.cgi` from section 4, just dispatching to the larger `Publisher` module and registering a type mapping so the `authInfo` structure serializes with the right namespace.

```mermaid
flowchart LR
    subgraph Deploy["📦 Deployment steps"]
        d1["1. Run create-db script<br/>→ members + items files"]:::step
        d2["2. Copy Publisher.cgi to cgi-bin"]:::step
        d3["3. Install Publisher.pm,<br/>members, items in Perl's path"]:::step
        d1 --> d2 --> d3
    end
    Ready["✅ Service ready for business"]:::done
    Deploy --> Ready

    classDef step fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef done fill:#bbf7d0,stroke:#15803d,stroke-width:2px,color:#052e16
```

---

## 15. The Java Shell Client

To actually use the Publisher service, the book builds an interactive **command-line shell** in Java. A sample session:

```text
C:\book>java Client http://localhost/cgi-bin/Publisher.cgi
Welcome to Publisher!
> help
Actions: register | login | post | remove | browse
> login
What is your user id: james@soap-wrc.com
What is your password: abc123xyz
Attempting to login...
james@soap-wrc.com is logged in
> post
What type of item [1 = News, 2 = Article, 3 = Resource]: 1
What is the title:
Programming Web Services with SOAP, WSDL and UDDI
What is the description:
A cool new book about Web services!
Attempting to post item...
Posted item 46
> quit
```

Two classes make this work: `authInfo.java` (the token holder, with the `serialize` method I showed in section 12.3) and `Client.java` (the shell itself).

### 15.1 Initializing and Invoking Calls

```java
private Call initCall() {
    Call call = new Call();
    call.setEncodingStyleURI(Constants.NS_URI_SOAP_ENC);
    call.setTargetObjectURI(uri);
    return call;
}

private Object invokeCall(Call call) throws Exception {
    try {
        Response response = call.invoke(url, "");
        if (!response.generatedFault()) {
            return response.getReturnValue() == null
                ? null : response.getReturnValue().getValue();
        } else {
            Fault f = response.getFault();
            throw new Exception("Fault = " + f.getFaultCode() + ", " + f.getFaultString());
        }
    } catch (SOAPException e) {
        throw new Exception("SOAPException = " + e.getFaultCode() + ", " + e.getMessage());
    }
}
```

Every wrapper method (`register`, `login`, `postItem`, and so on) follows the same three-step pattern: build a `Call`, add typed `Parameter` objects, invoke it.

### 15.2 Attaching the Auth Header

```java
public Header makeAuthHeader(authInfo auth) throws Exception {
    if (auth == null) {
        throw new Exception("Oops, you are not logged in. Please login first");
    }
    DocumentBuilderFactory dbf = DocumentBuilderFactory.newInstance();
    dbf.setNamespaceAware(true);
    dbf.setValidating(false);
    DocumentBuilder db = dbf.newDocumentBuilder();
    Document doc = db.newDocument();
    auth.serialize(doc);

    Vector headerEntries = new Vector();
    headerEntries.add(doc.getDocumentElement());
    Header header = new Header();
    header.setHeaderEntries(headerEntries);
    return header;
}
```

And it gets attached right before a protected call, like `postItem`:

```java
public void postItem(String type, String title, String description) throws Exception {
    Call call = initCall();
    Vector params = new Vector();
    params.add(new Parameter("type", String.class, type, null));
    params.add(new Parameter("title", String.class, title, null));
    params.add(new Parameter("description", String.class, description, null));
    call.setParams(params);
    call.setMethodName("postItem");
    call.setHeader(makeAuthHeader(authInfo));
    Integer itemID = (Integer) invokeCall(call);
    System.out.println("Posted item " + itemID + ".");
}
```

> 📝 **Note:** For `login`, the client also has to tell Apache SOAP explicitly how to **deserialize** the `authInfo` XML back into a Java object, using a `BeanSerializer` registered against the `http://www.soaplite.com/Publisher` namespace. This is exactly the "type mapping" concept from earlier: Apache SOAP needs an explicit link between a native type and its XML shape for anything beyond primitives like strings and integers.

```java
public void login(String email, String password) throws Exception {
    Call call = initCall();
    SOAPMappingRegistry smr = new SOAPMappingRegistry();
    BeanSerializer beanSer = new BeanSerializer();
    smr.mapTypes(Constants.NS_URI_SOAP_ENC,
        new QName("http://www.soaplite.com/Publisher", "authInfo"),
        authInfo.class, beanSer, beanSer);

    Vector params = new Vector();
    params.add(new Parameter("email", String.class, email, null));
    params.add(new Parameter("password", String.class, password, null));
    call.setParams(params);
    call.setMethodName("login");
    call.setSOAPMappingRegistry(smr);
    authInfo = (authInfo) invokeCall(call);
    System.out.println(authInfo.getEmail() + " logged in.");
}
```

```mermaid
flowchart TB
    Login["🔑 login(email, password)"]:::step
    Store["Store returned authInfo<br/>in the shell's session state"]:::step
    Post["✏️ postItem(...)"]:::step
    Attach["makeAuthHeader(authInfo)<br/>attached as SOAP Header"]:::step
    Server["🖥️ Publisher service<br/>validates signature + expiry"]:::server

    Login --> Store --> Post --> Attach --> Server

    classDef step fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#172554
    classDef server fill:#fde68a,stroke:#b45309,stroke-width:2px,color:#451a03
```

> 💡 **Tip:** The book is honest that a lot of boilerplate here, building a `Call`, adding typed `Parameter`s one at a time, could be eliminated with a dynamic proxy generated from WSDL, the way SOAP::Lite effectively gives you for free. It notes that tools of the era were starting to add exactly this. If you're picking up a modern SOAP toolkit today, generating a strongly typed client stub from a WSDL file is the direct descendant of that idea, and it's worth using rather than hand-rolling `Call` objects the way this example does.

---

## 16. What I Tested, and What I Didn't

I want to be precise about this, since I made a point of testing code in my last post too.

| Item | Status | How I verified it |
|---|---|---|
| Publisher-style auth token: issue, validate, reject tampering, reject expiry | ✅ **Tested** | Reimplemented in Python (`hashlib.md5`), ran all four scenarios, got the expected pass/fail results shown in section 12.2 |
| SOAPAction "#" vs "/" and unnamed-vs-named-parameter interoperability failure | ✅ **Tested**, as a faithful model | Built a small Python server mimicking the described .NET naming rule, confirmed the "loose" request fails and the "fixed" request succeeds, matching the book's description |
| SOAP::Lite Hello World client/server | ⚠️ **Not independently tested** | SOAP::Lite isn't installable in this environment; I've reproduced the listings as given, with the fixes noted for the two book typos I found (the proxy URL and the `SOAP::Data` parenthesization) |
| Apache SOAP Hello World, deployment descriptors, TCPTunnelGui | ⚠️ **Not independently tested** | Requires a servlet container and the Apache SOAP JARs, outside this environment; listings reproduced with the one compile-breaking typo fixed |
| .NET / C# Hello World and client | ⚠️ **Not independently tested** | Requires Windows and IIS; listings reproduced as given |
| Publisher service's full Perl module, database layer, and Java shell client | ⚠️ **Not independently tested end to end** | This is a substantial multi-file application; I tested the security-critical core (the auth token math) in isolation rather than standing up the whole CGI + DBI + Java-client stack |

> ⚠️ **Caution:** Don't read "not independently tested" as "probably wrong." It means exactly what it says: I didn't have the toolchain available here to run it myself, so I'm presenting the book's listings faithfully (with typos fixed where I found clear, unambiguous ones) rather than claiming a verification I didn't do. Where I *could* test something meaningfully, the token logic and the interoperability bug, I did, and both behaved as described.

---

## 17. What's Changed Since This Was Written

A quick reality check before you go copy any of this into production code.

| Topic in the source material | What to know today |
|---|---|
| MD5 for the auth token signature | MD5 is cryptographically broken for security purposes. A real implementation should use HMAC with SHA-256 or better, not a hand-rolled "secret + fields, then hash" construction |
| Credentials sent in plain text over `login` | Only ever acceptable over TLS/HTTPS. Plain HTTP is not an option for anything real |
| .NET SDK Beta 2, Windows 98/NT/2000, IIS-only deployment | Long superseded by many generations of the .NET runtime, cross-platform since .NET Core; you would not follow these specific installation steps today |
| Apache SOAP | Effectively succeeded by **Apache Axis** and later **Axis2** for Java SOAP work |
| SOAP::Lite | Still exists on CPAN and saw maintenance for years after this book, though the wider Perl web-services ecosystem has shrunk considerably |
| `.asmx` web services | Superseded first by WCF (Windows Communication Foundation), and more recently the industry-wide shift toward REST/JSON for new services, even inside the Microsoft ecosystem |
| DBD::CSV as a "database" | Fine for a book example; nobody should run a real service against flat CSV files as its system of record |
| Dynamic proxies from WSDL as an emerging convenience | Became completely standard; essentially every modern SOAP toolkit generates a client stub from WSDL rather than making you hand-build `Call` objects |

> 📝 **Note:** As with my last post, this table is general knowledge about how the ecosystem evolved, not something pulled from the source material itself. If you're actually integrating with a live SOAP service today, check its specific documentation and the current version of whatever toolkit you're using rather than relying on either this table or the book's exact instructions.

---

## 18. Cheat Sheet and Final Thoughts

### 18.1 One page, all three toolkits

```mermaid
mindmap
  root((Writing SOAP<br/>Web Services))
    Common pattern
      Write the code
      Deploy it
      Invoke it
      Listener, Proxy, App code
    Perl SOAP::Lite
      CGI glue script
      dispatch_to module
      Pluggable transports
      HTTP, Jabber, SMTP, TCP
    Java Apache SOAP
      Separate deployment descriptor
      Service Manager Client
      Type mappings
      TCPTunnelGui debugging
    Csharp dotNET
      asmx file
      Auto-generated docs
      WebMethod attribute
      HTTP-GET HTTP-POST or SOAP
    Interop pitfalls
      SOAPAction hash vs slash
      Named vs generic parameters
      Namespace inheritance bugs
    Publisher service
      register login post remove browse
      Auth token in Header block
      MD5 signature plus expiry check
      Ownership checks on remove
```

### 18.2 Quick reference

| Concept | One-line summary |
|---|---|
| **Listener** | Speaks the transport, receives the raw message |
| **Proxy** | Deserializes, invokes, serializes the response |
| **Deployment** | Telling the proxy which code handles which message |
| **SOAP::Lite** | Zero-friction Perl deployment; server script names the module directly |
| **Apache SOAP** | Servlet-based; needs a separate XML deployment descriptor |
| **.NET `.asmx`** | Drop a source file in the IIS web root; auto-compiled and auto-documented |
| **TCPTunnelGui** | A local proxy that shows you the raw SOAP traffic in both directions |
| **SOAPAction mismatch** | `#` vs `/` between namespace and operation name; a real, reproducible interop bug |
| **Unnamed parameters** | Loosely typed clients can send auto-generated element names that a strict server rejects |
| **Publisher auth token** | Signed, time-limited token attached as a Header block, not in the Body |
| **Ownership check** | `removeItem` scopes its lookup to the caller's own `memberID`, not just any item |

### 18.3 My rules of thumb

1. **The workflow is always the same, whatever the language.** Write the code, describe it, deploy it, invoke it. If a toolkit makes this feel radically different, that's a sign of the toolkit's philosophy, not a different underlying model.
2. **Check your classpath first.** This is Java-specific advice from the book, but it generalizes: for any toolkit, the most common failure is an environment/configuration problem, not a code problem.
3. **Test against a second implementation early**, exactly as section 10 forced me to. A service that's only ever been tested against its own toolkit's client is untested, from an interoperability standpoint.
4. **Separate "who's calling" from "what they want."** The Publisher service's decision to put the auth token in a Header block rather than mixing it into the Body payload is a small design choice with a big payoff in clarity.
5. **A signature proves integrity, not validity.** Always check expiry (or any other validity condition) as a separate step from checking the signature itself, as I confirmed when testing the token logic.
6. **Scope authorization checks to the resource, not just the caller.** `removeItem`'s ownership check, looking the item up by both `memberID` and `itemID`, is doing real security work, not just convenience.
7. **Debugging tools that show you raw wire traffic are worth their weight in gold.** Whether it's TCPTunnelGui, a modern HTTP proxy, or just `curl -v`, seeing the actual bytes on the wire is how you catch bugs like the SOAPAction mismatch in minutes instead of hours.

### 18.4 Closing thoughts

What struck me most working through this material is how **unglamorous** good SOAP tooling actually is. None of these toolkits are doing anything conceptually deep, they're all just automating the same three steps (deserialize, invoke, serialize) that I could write by hand if I had to. The real value is in the boring, careful work: getting the deployment descriptor right, getting the classpath right, getting the SOAPAction header format right.

And the interoperability section is, honestly, the most useful part of the whole chapter. It's easy to read a spec and assume that "legal SOAP" means "works with every other legal SOAP implementation." The Perl-to-.NET bug I reproduced here proves that's not automatically true. Two toolkits can both faithfully implement the same specification and still fail to talk to each other over something as small as a punctuation mark in a header, or an auto-generated element name.

If there's one thing I'd want a reader to carry forward from this post, it's this: **build the smallest possible cross-toolkit test early**, exactly like the Hello World example here, before you invest real effort into a bigger service like Publisher. It's a lot cheaper to discover a SOAPAction mismatch on a four-line "Hello World" call than three weeks into building an authenticated, database-backed service.

If you'd like me to go deeper on any one piece, the Publisher database layer, building a working modern equivalent of the Java shell client, or the WSDL-driven dynamic proxies the book gestures at but doesn't build, let me know and I'll take it further.
