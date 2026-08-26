#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# AWS Infrastructure Bootstrap — BoldMark PMS
# ═══════════════════════════════════════════════════════════════════════
# Provisions ALL the AWS infrastructure needed to deploy BoldMark.
# Idempotent: safe to re-run. Will skip resources that already exist.
#
# What it creates in eu-west-2 (London):
#   1. IAM user "boldmark-deployer" with policy for ECR push + EC2 SSM
#   2. ECR repositories: boldmark-app, boldmark-nginx
#   3. S3 buckets: boldmark-uploads-<account_id>, boldmark-db-backups-<account_id>
#   4. EC2 key pair "boldmark-deploy" (private key written to ./boldmark-ec2.pem)
#   5. VPC security group "boldmark-sg" (open: 22, 80, 443)
#   6. EC2 instance: t3.small, Ubuntu 24.04 LTS, 30 GB gp3 EBS, default VPC
#   7. Elastic IP attached to the instance
#
# At the end, prints every value you need to paste into GitHub Secrets.
#
# ── Prerequisites ──────────────────────────────────────────────────────
#   - Docker installed and running (Docker Desktop, Engine, etc.)
#   - AWS root account or admin IAM user with the following env vars set:
#         AWS_ACCESS_KEY_ID
#         AWS_SECRET_ACCESS_KEY
#     (The script uses these to provision a *separate* deployer user.)
#
# ── Usage ──────────────────────────────────────────────────────────────
#   export AWS_ACCESS_KEY_ID=AKIA...
#   export AWS_SECRET_ACCESS_KEY=...
#   bash scripts/aws-bootstrap.sh             # normal run (idempotent)
#   bash scripts/aws-bootstrap.sh --rotate-key # delete+recreate deployer IAM key
#
# ── Cost estimate (eu-west-2, on-demand) ──────────────────────────────
#   t3.small EC2          ~ $15/mo  (or $9/mo with a 1-year reserved instance)
#   30 GB gp3 EBS         ~ $2.40/mo
#   Elastic IP (attached) free
#   ECR (10 GB stored)    ~ $1/mo
#   S3 (10 GB + traffic)  ~ $0.30/mo + $0.09/GB egress
#   Data transfer out     ~ varies
#   ──────────────────────
#   Total                 ~ $19-22/mo at low traffic
#                         ~ $13-16/mo with 1-year reserved instance
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

# ── Flags ─────────────────────────────────────────────────────────────
ROTATE_KEY=false
for arg in "$@"; do
  case "$arg" in
    --rotate-key) ROTATE_KEY=true ;;
  esac
done

# ── Configuration ─────────────────────────────────────────────────────
REGION="${AWS_REGION:-eu-west-2}"
PROJECT="boldmark"
DEPLOYER_USER="${PROJECT}-deployer"
KEY_PAIR_NAME="${PROJECT}-deploy"
SECURITY_GROUP_NAME="${PROJECT}-sg"
INSTANCE_TYPE="${INSTANCE_TYPE:-t3.small}"
INSTANCE_NAME="${PROJECT}-prod"
ECR_APP_REPO="${PROJECT}-app"
ECR_NGINX_REPO="${PROJECT}-nginx"
EC2_ROLE_NAME="${PROJECT}-ec2-ecr-role"
EC2_INSTANCE_PROFILE="${PROJECT}-ec2-ecr-role"
DOMAIN="portal.boldmarkprop.co.za"

# Where this script is being run (so we can write the key file & secrets file there)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
KEY_FILE="${SCRIPT_DIR}/${PROJECT}-ec2.pem"
SECRETS_FILE="${SCRIPT_DIR}/.aws-bootstrap-output.txt"

# ── Pre-flight checks ─────────────────────────────────────────────────
require() {
  command -v "$1" >/dev/null 2>&1 || { echo "ERROR: '$1' not installed."; exit 1; }
}
require docker
require jq

if ! docker info >/dev/null 2>&1; then
  echo "ERROR: Docker daemon is not running. Start Docker Desktop and re-run."
  exit 1
fi

if [ -z "${AWS_ACCESS_KEY_ID:-}" ] || [ -z "${AWS_SECRET_ACCESS_KEY:-}" ]; then
  cat <<EOF
ERROR: AWS credentials not set in environment.

Export your AWS root or admin access key first:
  export AWS_ACCESS_KEY_ID=AKIA...
  export AWS_SECRET_ACCESS_KEY=...

Then re-run this script.
EOF
  exit 1
fi

# ── AWS CLI wrapper (runs in Docker, no local install needed) ─────────
aws() {
  docker run --rm \
    -e AWS_ACCESS_KEY_ID \
    -e AWS_SECRET_ACCESS_KEY \
    -e AWS_DEFAULT_REGION="$REGION" \
    -e AWS_PAGER="" \
    -v "$SCRIPT_DIR:/work" \
    -w /work \
    amazon/aws-cli:latest "$@"
}

banner() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "  $1"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# ── 0. Verify caller identity ─────────────────────────────────────────
banner "0. Verifying AWS credentials..."
CALLER_INFO=$(aws sts get-caller-identity)
ACCOUNT_ID=$(echo "$CALLER_INFO" | jq -r '.Account')
CALLER_ARN=$(echo "$CALLER_INFO" | jq -r '.Arn')
echo "  Account ID : $ACCOUNT_ID"
echo "  Caller ARN : $CALLER_ARN"
echo "  Region     : $REGION"

# ── 1. IAM user for GitHub Actions ────────────────────────────────────
banner "1. Creating IAM user '$DEPLOYER_USER'..."
if aws iam get-user --user-name "$DEPLOYER_USER" >/dev/null 2>&1; then
  echo "  ✓ User already exists"
else
  aws iam create-user --user-name "$DEPLOYER_USER" >/dev/null
  echo "  ✓ Created user"
fi

# Attach inline policy: ECR push + read, S3 read/write to project buckets
DEPLOYER_POLICY=$(cat <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ECRAuth",
      "Effect": "Allow",
      "Action": ["ecr:GetAuthorizationToken"],
      "Resource": "*"
    },
    {
      "Sid": "ECRRepoOps",
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:CompleteLayerUpload",
        "ecr:CreateRepository",
        "ecr:DescribeRepositories",
        "ecr:DescribeImages",
        "ecr:BatchDeleteImage",
        "ecr:GetDownloadUrlForLayer",
        "ecr:InitiateLayerUpload",
        "ecr:PutImage",
        "ecr:PutImageScanningConfiguration",
        "ecr:UploadLayerPart",
        "ecr:BatchGetImage"
      ],
      "Resource": "arn:aws:ecr:${REGION}:${ACCOUNT_ID}:repository/${PROJECT}-*"
    },
    {
      "Sid": "S3Buckets",
      "Effect": "Allow",
      "Action": [
        "s3:ListBucket",
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::${PROJECT}-uploads-${ACCOUNT_ID}",
        "arn:aws:s3:::${PROJECT}-uploads-${ACCOUNT_ID}/*",
        "arn:aws:s3:::${PROJECT}-db-backups-${ACCOUNT_ID}",
        "arn:aws:s3:::${PROJECT}-db-backups-${ACCOUNT_ID}/*"
      ]
    }
  ]
}
EOF
)
echo "$DEPLOYER_POLICY" > /tmp/deployer-policy.json
docker run --rm \
  -e AWS_ACCESS_KEY_ID \
  -e AWS_SECRET_ACCESS_KEY \
  -e AWS_DEFAULT_REGION="$REGION" \
  -e AWS_PAGER="" \
  -v /tmp:/tmp \
  amazon/aws-cli:latest \
  iam put-user-policy \
    --user-name "$DEPLOYER_USER" \
    --policy-name "${PROJECT}-deploy-policy" \
    --policy-document file:///tmp/deployer-policy.json
echo "  ✓ Inline policy attached"

# Create / rotate access key for the deployer
EXISTING_KEYS=$(aws iam list-access-keys --user-name "$DEPLOYER_USER" --query 'AccessKeyMetadata[*].AccessKeyId' --output text)
EXISTING_KEY_COUNT=$(echo "${EXISTING_KEYS}" | wc -w | tr -d ' ')
if [ "$ROTATE_KEY" = "true" ] && [ "$EXISTING_KEY_COUNT" -gt 0 ]; then
  for old_key_id in $EXISTING_KEYS; do
    aws iam delete-access-key --user-name "$DEPLOYER_USER" --access-key-id "$old_key_id" >/dev/null
    echo "  ✓ Deleted old access key $old_key_id"
  done
  EXISTING_KEY_COUNT=0
fi
if [ "$EXISTING_KEY_COUNT" = "0" ]; then
  KEY_OUTPUT=$(aws iam create-access-key --user-name "$DEPLOYER_USER")
  DEPLOYER_ACCESS_KEY_ID=$(echo "$KEY_OUTPUT" | jq -r '.AccessKey.AccessKeyId')
  DEPLOYER_SECRET_ACCESS_KEY=$(echo "$KEY_OUTPUT" | jq -r '.AccessKey.SecretAccessKey')
  echo "  ✓ Access key created (will be shown at end)"
else
  DEPLOYER_ACCESS_KEY_ID="<EXISTING — run with --rotate-key to replace>"
  DEPLOYER_SECRET_ACCESS_KEY="<EXISTING — run with --rotate-key to replace>"
  echo "  ⚠ User already has access key(s). Re-run with --rotate-key to get fresh credentials."
fi

# ── 2. ECR repositories ───────────────────────────────────────────────
banner "2. Creating ECR repositories..."
for repo in "$ECR_APP_REPO" "$ECR_NGINX_REPO"; do
  if aws ecr describe-repositories --repository-names "$repo" >/dev/null 2>&1; then
    echo "  ✓ $repo (exists)"
  else
    aws ecr create-repository \
      --repository-name "$repo" \
      --image-scanning-configuration scanOnPush=true \
      --image-tag-mutability MUTABLE >/dev/null
    echo "  ✓ $repo (created)"
  fi
done
ECR_REGISTRY="${ACCOUNT_ID}.dkr.ecr.${REGION}.amazonaws.com"

# ── 3. S3 buckets ─────────────────────────────────────────────────────
banner "3. Creating S3 buckets..."
UPLOADS_BUCKET="${PROJECT}-uploads-${ACCOUNT_ID}"
BACKUPS_BUCKET="${PROJECT}-db-backups-${ACCOUNT_ID}"

for bucket in "$UPLOADS_BUCKET" "$BACKUPS_BUCKET"; do
  if aws s3api head-bucket --bucket "$bucket" >/dev/null 2>&1; then
    echo "  ✓ $bucket (exists)"
  else
    aws s3api create-bucket \
      --bucket "$bucket" \
      --region "$REGION" \
      --create-bucket-configuration LocationConstraint="$REGION" >/dev/null
    # Block public access by default (private bucket)
    aws s3api put-public-access-block \
      --bucket "$bucket" \
      --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true >/dev/null
    # Default encryption
    aws s3api put-bucket-encryption \
      --bucket "$bucket" \
      --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}' >/dev/null
    echo "  ✓ $bucket (created, private, encrypted)"
  fi
done

# Lifecycle on backups: transition to IA after 30 days, delete after 365
aws s3api put-bucket-lifecycle-configuration \
  --bucket "$BACKUPS_BUCKET" \
  --lifecycle-configuration '{
    "Rules": [{
      "ID": "expire-old-backups",
      "Status": "Enabled",
      "Filter": {"Prefix": "database/"},
      "Transitions": [{"Days": 30, "StorageClass": "STANDARD_IA"}],
      "Expiration": {"Days": 365}
    }]
  }' >/dev/null
echo "  ✓ Backup bucket lifecycle: IA after 30d, delete after 365d"

# ── 4. EC2 key pair ───────────────────────────────────────────────────
banner "4. Creating EC2 key pair '$KEY_PAIR_NAME'..."
if aws ec2 describe-key-pairs --key-names "$KEY_PAIR_NAME" >/dev/null 2>&1; then
  echo "  ✓ Key pair exists"
  if [ ! -f "$KEY_FILE" ]; then
    echo "  ⚠ Local PEM file missing at $KEY_FILE — you can't SSH without it."
    echo "    To re-issue: aws ec2 delete-key-pair --key-name $KEY_PAIR_NAME, then re-run this script."
  fi
else
  aws ec2 create-key-pair \
    --key-name "$KEY_PAIR_NAME" \
    --key-type rsa \
    --key-format pem \
    --query 'KeyMaterial' \
    --output text > "$KEY_FILE"
  chmod 600 "$KEY_FILE"
  echo "  ✓ Key pair created → $KEY_FILE (chmod 600)"
fi

# ── 5. Security group ─────────────────────────────────────────────────
banner "5. Creating security group '$SECURITY_GROUP_NAME'..."
DEFAULT_VPC_ID=$(aws ec2 describe-vpcs --filters Name=is-default,Values=true --query 'Vpcs[0].VpcId' --output text)
if [ "$DEFAULT_VPC_ID" = "None" ] || [ -z "$DEFAULT_VPC_ID" ]; then
  echo "ERROR: No default VPC found in $REGION. Create one in the AWS console first."
  exit 1
fi
echo "  Default VPC: $DEFAULT_VPC_ID"

EXISTING_SG=$(aws ec2 describe-security-groups \
  --filters "Name=group-name,Values=$SECURITY_GROUP_NAME" "Name=vpc-id,Values=$DEFAULT_VPC_ID" \
  --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || echo "None")

if [ "$EXISTING_SG" != "None" ] && [ -n "$EXISTING_SG" ]; then
  SG_ID="$EXISTING_SG"
  echo "  ✓ Security group exists: $SG_ID"
else
  SG_ID=$(aws ec2 create-security-group \
    --group-name "$SECURITY_GROUP_NAME" \
    --description "BoldMark PMS production EC2 - SSH, HTTP, HTTPS" \
    --vpc-id "$DEFAULT_VPC_ID" \
    --query 'GroupId' --output text)
  echo "  ✓ Security group created: $SG_ID"

  # Open ports 22, 80, 443 from anywhere (0.0.0.0/0)
  for port in 22 80 443; do
    aws ec2 authorize-security-group-ingress \
      --group-id "$SG_ID" \
      --protocol tcp --port "$port" --cidr 0.0.0.0/0 >/dev/null
  done
  echo "  ✓ Inbound rules: SSH (22), HTTP (80), HTTPS (443) from 0.0.0.0/0"
fi

# ── 5.5 IAM instance profile for ECR pull ─────────────────────────────
# The EC2 box pulls images from ECR using this role (no long-lived keys on
# the server). Mirrors the telcoflo-ec2-ecr-role / perfectorder-ec2-ecr-role
# pattern already in this account.
banner "5.5 Creating EC2 instance profile '$EC2_ROLE_NAME' (ECR read)..."
EC2_TRUST_POLICY='{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {"Service": "ec2.amazonaws.com"},
    "Action": "sts:AssumeRole"
  }]
}'
if aws iam get-role --role-name "$EC2_ROLE_NAME" >/dev/null 2>&1; then
  echo "  ✓ Role exists"
else
  echo "$EC2_TRUST_POLICY" > /tmp/ec2-trust-policy.json
  docker run --rm \
    -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY \
    -e AWS_DEFAULT_REGION="$REGION" -e AWS_PAGER="" \
    -v /tmp:/tmp amazon/aws-cli:latest \
    iam create-role --role-name "$EC2_ROLE_NAME" \
      --assume-role-policy-document file:///tmp/ec2-trust-policy.json >/dev/null
  echo "  ✓ Role created"
fi
aws iam attach-role-policy --role-name "$EC2_ROLE_NAME" \
  --policy-arn arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryReadOnly >/dev/null
echo "  ✓ AmazonEC2ContainerRegistryReadOnly attached"

if aws iam get-instance-profile --instance-profile-name "$EC2_INSTANCE_PROFILE" >/dev/null 2>&1; then
  echo "  ✓ Instance profile exists"
else
  aws iam create-instance-profile --instance-profile-name "$EC2_INSTANCE_PROFILE" >/dev/null
  echo "  ✓ Instance profile created"
fi
# Add role to profile (ignore error if already added), then wait for propagation
aws iam add-role-to-instance-profile \
  --instance-profile-name "$EC2_INSTANCE_PROFILE" \
  --role-name "$EC2_ROLE_NAME" >/dev/null 2>&1 || true
echo "  ✓ Role bound to instance profile (allowing 10s for IAM propagation)"
sleep 10

# ── 6. EC2 instance ───────────────────────────────────────────────────
banner "6. Launching EC2 instance '$INSTANCE_NAME'..."

# Find latest Ubuntu 24.04 LTS AMI for this region (Canonical owner: 099720109477)
AMI_ID=$(aws ec2 describe-images \
  --owners 099720109477 \
  --filters "Name=name,Values=ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*" \
            "Name=state,Values=available" \
            "Name=architecture,Values=x86_64" \
  --query 'sort_by(Images, &CreationDate) | [-1].ImageId' \
  --output text)
echo "  Latest Ubuntu 24.04 AMI: $AMI_ID"

EXISTING_INSTANCE=$(aws ec2 describe-instances \
  --filters "Name=tag:Name,Values=$INSTANCE_NAME" "Name=instance-state-name,Values=pending,running,stopping,stopped" \
  --query 'Reservations[0].Instances[0].InstanceId' --output text 2>/dev/null || echo "None")

if [ "$EXISTING_INSTANCE" != "None" ] && [ -n "$EXISTING_INSTANCE" ]; then
  INSTANCE_ID="$EXISTING_INSTANCE"
  echo "  ✓ Instance already exists: $INSTANCE_ID"
else
  INSTANCE_ID=$(aws ec2 run-instances \
    --image-id "$AMI_ID" \
    --count 1 \
    --instance-type "$INSTANCE_TYPE" \
    --key-name "$KEY_PAIR_NAME" \
    --security-group-ids "$SG_ID" \
    --iam-instance-profile "Name=$EC2_INSTANCE_PROFILE" \
    --block-device-mappings '[{"DeviceName":"/dev/sda1","Ebs":{"VolumeSize":30,"VolumeType":"gp3","DeleteOnTermination":true}}]' \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=$INSTANCE_NAME},{Key=Project,Value=$PROJECT}]" \
    --query 'Instances[0].InstanceId' --output text)
  echo "  ✓ Instance launched: $INSTANCE_ID"
  echo "  Waiting for instance to enter running state..."
  aws ec2 wait instance-running --instance-ids "$INSTANCE_ID"
  echo "  ✓ Instance is running"
fi

# Ensure the ECR instance profile is associated (covers pre-existing instances
# created before this role was added — safe no-op if already associated).
CURRENT_PROFILE=$(aws ec2 describe-iam-instance-profile-associations \
  --filters "Name=instance-id,Values=$INSTANCE_ID" \
  --query 'IamInstanceProfileAssociations[?State==`associated`].IamInstanceProfile.Arn' \
  --output text 2>/dev/null || echo "")
if echo "$CURRENT_PROFILE" | grep -q "$EC2_INSTANCE_PROFILE"; then
  echo "  ✓ Instance profile already associated"
else
  aws ec2 associate-iam-instance-profile \
    --instance-id "$INSTANCE_ID" \
    --iam-instance-profile "Name=$EC2_INSTANCE_PROFILE" >/dev/null 2>&1 \
    && echo "  ✓ Instance profile associated with $INSTANCE_ID" \
    || echo "  ⚠ Could not associate instance profile (may already be attached)"
fi

# ── 7. Elastic IP ─────────────────────────────────────────────────────
banner "7. Allocating Elastic IP and attaching..."
EXISTING_EIP=$(aws ec2 describe-addresses \
  --filters "Name=tag:Name,Values=${PROJECT}-eip" \
  --query 'Addresses[0].PublicIp' --output text 2>/dev/null || echo "None")

if [ "$EXISTING_EIP" != "None" ] && [ -n "$EXISTING_EIP" ]; then
  ELASTIC_IP="$EXISTING_EIP"
  ALLOC_ID=$(aws ec2 describe-addresses --public-ips "$ELASTIC_IP" --query 'Addresses[0].AllocationId' --output text)
  echo "  ✓ Elastic IP exists: $ELASTIC_IP"
else
  ALLOC_RESULT=$(aws ec2 allocate-address --domain vpc --tag-specifications "ResourceType=elastic-ip,Tags=[{Key=Name,Value=${PROJECT}-eip},{Key=Project,Value=$PROJECT}]")
  ELASTIC_IP=$(echo "$ALLOC_RESULT" | jq -r '.PublicIp')
  ALLOC_ID=$(echo "$ALLOC_RESULT" | jq -r '.AllocationId')
  echo "  ✓ Elastic IP allocated: $ELASTIC_IP"
fi

# Attach EIP to instance (no-op if already attached to this instance)
CURRENT_ATTACHMENT=$(aws ec2 describe-addresses --allocation-ids "$ALLOC_ID" --query 'Addresses[0].InstanceId' --output text 2>/dev/null || echo "None")
if [ "$CURRENT_ATTACHMENT" != "$INSTANCE_ID" ]; then
  aws ec2 associate-address --instance-id "$INSTANCE_ID" --allocation-id "$ALLOC_ID" >/dev/null
  echo "  ✓ Elastic IP attached to $INSTANCE_ID"
else
  echo "  ✓ Elastic IP already attached to $INSTANCE_ID"
fi

# ── 8. Output GitHub secrets ──────────────────────────────────────────
banner "8. Bootstrap complete!"

cat > "$SECRETS_FILE" <<EOF
═══════════════════════════════════════════════════════════════════════
  BoldMark PMS — AWS Bootstrap Output
  Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
═══════════════════════════════════════════════════════════════════════

ACCOUNT INFO
  AWS Account ID    : $ACCOUNT_ID
  Region            : $REGION
  ECR Registry      : $ECR_REGISTRY
  EC2 Public IP     : $ELASTIC_IP
  EC2 Instance ID   : $INSTANCE_ID
  S3 Uploads Bucket : $UPLOADS_BUCKET
  S3 Backups Bucket : $BACKUPS_BUCKET

GITHUB SECRETS — paste these into:
  https://github.com/<owner>/<repo>/settings/secrets/actions

  AWS_ACCESS_KEY_ID         = $DEPLOYER_ACCESS_KEY_ID
  AWS_SECRET_ACCESS_KEY     = $DEPLOYER_SECRET_ACCESS_KEY
  EC2_HOST                  = $ELASTIC_IP
  EC2_USER                  = ubuntu
  EC2_SSH_KEY               = <contents of $KEY_FILE — see below>
  EC2_SSH_PORT              = 22
  PRODUCTION_ENV            = <full .env file contents — template below>
  VITE_PUSHER_APP_KEY       = <your Pusher key>
  VITE_PUSHER_APP_CLUSTER   = eu

EC2_SSH_KEY VALUE
  Run:  cat $KEY_FILE
  Paste the ENTIRE output (including BEGIN/END lines) into the secret.

PRODUCTION_ENV TEMPLATE (paste & fill in real values for the secret):
─────────────────────────────────────────────────────────────────────
APP_NAME="BoldMark PMS"
APP_ENV=production
APP_KEY=<run: docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));">
APP_DEBUG=false
APP_URL=https://$DOMAIN
FRONTEND_URL=https://$DOMAIN
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=boldmark
DB_USERNAME=boldmark
DB_PASSWORD=<generate strong password>
DB_ROOT_PASSWORD=<generate strong password>

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_LIFETIME=120

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=<generate strong password>

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=<from Pusher dashboard>
PUSHER_APP_KEY=<from Pusher dashboard>
PUSHER_APP_SECRET=<from Pusher dashboard>
PUSHER_APP_CLUSTER=eu

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=$DEPLOYER_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY=$DEPLOYER_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION=$REGION
AWS_BUCKET=$UPLOADS_BUCKET

MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@boldmarkprop.co.za"
MAIL_FROM_NAME="\${APP_NAME}"
RESEND_API_KEY=<your Resend API key>
RESEND_WEBHOOK_SECRET=<your Resend webhook secret>

HORIZON_NAME="BoldMark Horizon"

BACKUP_S3_BUCKET=$BACKUPS_BUCKET
─────────────────────────────────────────────────────────────────────

NEXT STEPS
  1. SSH into the EC2 instance and run the EC2 bootstrap:
       ssh -i $KEY_FILE ubuntu@$ELASTIC_IP
       sudo bash <(curl -fsSL https://raw.githubusercontent.com/<owner>/<repo>/main/docker/scripts/setup-ec2.sh)
       # — OR —
       scp -i $KEY_FILE docker/scripts/setup-ec2.sh ubuntu@$ELASTIC_IP:/tmp/
       ssh -i $KEY_FILE ubuntu@$ELASTIC_IP "sudo bash /tmp/setup-ec2.sh"

  2. Point your domain at the new Elastic IP:
       Type    : A
       Name    : portal
       Value   : $ELASTIC_IP
       TTL     : 300

  3. Add the GitHub secrets listed above.

  4. Push to main — your CI/CD pipeline will build & deploy automatically.

  5. After the FIRST successful deploy, run on the EC2 instance:
       ssh -i $KEY_FILE ubuntu@$ELASTIC_IP
       sudo bash /opt/boldmark/docker/scripts/first-deploy.sh

═══════════════════════════════════════════════════════════════════════
EOF

cat "$SECRETS_FILE"

echo ""
echo "Output also saved to: $SECRETS_FILE"
echo "(This file is gitignored. Treat its contents as secret.)"
