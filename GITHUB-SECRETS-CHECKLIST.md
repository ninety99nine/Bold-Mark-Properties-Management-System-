# GitHub Secrets Checklist

Go to: https://github.com/ninety99nine/Bold-Mark-Properties-Management-System-/settings/secrets/actions

## Required secrets

| Secret | Value | Source |
|--------|-------|--------|
| `AWS_ACCESS_KEY_ID` | boldmark-deployer key ID | Re-run `bash scripts/aws-bootstrap.sh --rotate-key` |
| `AWS_SECRET_ACCESS_KEY` | boldmark-deployer secret | Same run as above |
| `EC2_HOST` | Elastic IP address | AWS Console → EC2 → Elastic IPs (or bootstrap output) |
| `EC2_USER` | `ubuntu` | Fixed value |
| `EC2_SSH_KEY` | Content of `scripts/boldmark-ec2.pem` | `cat scripts/boldmark-ec2.pem` |
| `PRODUCTION_ENV` | Entire filled-in `.env.production.example` | See instructions in that file |
| `VITE_PUSHER_APP_KEY` | Pusher app key | pusher.com dashboard |
| `VITE_PUSHER_APP_CLUSTER` | `eu` | pusher.com dashboard |

## Values you already know

- **AWS_BUCKET**: `boldmark-uploads-923764751748` (created by bootstrap)
- **AWS_DEFAULT_REGION**: `eu-west-2` (in the env file, not a secret)
- **RESEND_API_KEY**: already in your local `.env` — copy to PRODUCTION_ENV
- **RESEND_WEBHOOK_SECRET**: already in your local `.env` — copy to PRODUCTION_ENV
- **EC2_USER**: `ubuntu` (fixed for Ubuntu AMI)

## Values still needed

- **APP_KEY**: generate with `php artisan key:generate --show` inside the container after first deploy, OR generate locally and paste into PRODUCTION_ENV
- **DB_PASSWORD / DB_ROOT_PASSWORD**: choose strong passwords, put in PRODUCTION_ENV
- **REDIS_PASSWORD**: choose a strong password, put in PRODUCTION_ENV
- **Pusher credentials**: create a free Pusher Channels app at pusher.com

## After secrets are set — first deploy sequence

1. AWS unblocks EC2 → run bootstrap to create instance + get deployer key
2. Note the Elastic IP from bootstrap output → set `EC2_HOST` secret
3. SSH into EC2: `ssh -i scripts/boldmark-ec2.pem ubuntu@<ELASTIC_IP>`
4. Run: `bash /opt/boldmark/docker/scripts/setup-ec2.sh`
5. Merge `phase-2` → `main` on GitHub → CI runs → images built → deployed
6. Run: `bash /opt/boldmark/docker/scripts/first-deploy.sh` (migrations + seed)
7. Point DNS `portal.boldmarkprop.co.za` → Elastic IP (A record)
8. SSL cert auto-issues via certbot on first nginx start
