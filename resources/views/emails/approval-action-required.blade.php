<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Required</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f7f6f1;
            color: #2d2620;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fef9f5;
            border: 1px solid #e8e0d6;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #006e6e 0%, #00504f 100%);
            padding: 32px 24px;
            text-align: center;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 32px 24px;
        }
        .greeting {
            margin: 0 0 20px 0;
            font-size: 16px;
            line-height: 1.5;
        }
        .greeting strong {
            color: #006e6e;
        }
        .action-section {
            margin: 24px 0;
            padding: 20px;
            background-color: #ecf6f6;
            border-left: 4px solid #006e6e;
            border-radius: 4px;
        }
        .action-title {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #006e6e;
        }
        .action-message {
            margin: 0;
            font-size: 16px;
            line-height: 1.6;
            color: #2d2620;
        }
        .document-info {
            margin: 24px 0;
            padding: 16px;
            background-color: #f0ece4;
            border-radius: 6px;
        }
        .document-label {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7a7268;
            margin-bottom: 8px;
        }
        .document-name {
            font-size: 16px;
            font-weight: 500;
            color: #2d2620;
            margin: 0;
            word-break: break-word;
        }
        .cta-button {
            display: inline-block;
            margin: 24px 0;
            padding: 12px 32px;
            background-color: #006e6e;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: -0.3px;
            transition: background-color 0.2s;
        }
        .cta-button:hover {
            background-color: #00504f;
        }
        .step-info {
            margin: 16px 0 0 0;
            font-size: 14px;
            color: #7a7268;
        }
        .divider {
            margin: 24px 0;
            border: none;
            border-top: 1px solid #e8e0d6;
        }
        .footer {
            padding: 20px 24px;
            background-color: #f7f6f1;
            text-align: center;
            font-size: 13px;
            color: #7a7268;
            line-height: 1.6;
        }
        .footer a {
            color: #006e6e;
            text-decoration: none;
        }
        .context-overdue {
            border-left-color: #d97706;
        }
        .context-overdue .action-title {
            color: #d97706;
        }
        .header.context-overdue {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $context === 'overdue' ? 'context-overdue' : '' }}">
            <h1>
                @if($context === 'reassigned')
                    📋 Reassigned to You
                @elseif($context === 'overdue')
                    ⏰ Action Overdue
                @else
                    ✓ Your Turn to Review
                @endif
            </h1>
        </div>

        <div class="content">
            <p class="greeting">Hello <strong>{{ $recipientName }},</strong></p>

            <div class="action-section {{ $context === 'overdue' ? 'context-overdue' : '' }}">
                <div class="action-title">
                    @if($context === 'reassigned')
                        Reassigned to you
                    @elseif($context === 'overdue')
                        Decision overdue
                    @else
                        Your action needed
                    @endif
                </div>
                <p class="action-message">
                    @if($context === 'reassigned')
                        A pending approval decision has been reassigned to you. Please review the document and record your decision.
                    @elseif($context === 'overdue')
                        Your decision on this document is overdue. Please review and sign as soon as possible.
                    @else
                        This document is ready for your review and approval.{{ $stepName ? " You are reviewing the \"" . $stepName . "\" step." : '' }}
                    @endif
                </p>
            </div>

            <div class="document-info">
                <div class="document-label">Document</div>
                <p class="document-name">{{ $documentLabel }}</p>
            </div>

            <div style="text-align: center;">
                <a href="{{ $documentUrl }}" class="cta-button">Review & Sign</a>
                <p class="step-info">
                    @if($stepName)
                        Step: <strong>{{ $stepName }}</strong>
                    @endif
                </p>
            </div>

            <hr class="divider">

            <p style="margin: 0; font-size: 14px; color: #7a7268; line-height: 1.6;">
                If you have any questions about this document or need assistance, please contact your administrator or the document submitter.
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 8px 0;">
                {{ config('app.name') }}
            </p>
            <p style="margin: 0; font-size: 12px;">
                This is an automated notification. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
