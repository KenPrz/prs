<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document {{ ucfirst($outcome) }}</title>
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
            padding: 32px 24px;
            text-align: center;
            color: white;
        }
        .header.approved {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        }
        .header.rejected {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        }
        .header.cancelled {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
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
        .outcome-section {
            margin: 24px 0;
            padding: 20px;
            border-left: 4px solid #006e6e;
            border-radius: 4px;
        }
        .outcome-section.approved {
            background-color: #ecfdf5;
            border-left-color: #047857;
        }
        .outcome-section.rejected {
            background-color: #fef3c7;
            border-left-color: #d97706;
        }
        .outcome-section.cancelled {
            background-color: #f3f4f6;
            border-left-color: #6b7280;
        }
        .outcome-title {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #006e6e;
        }
        .outcome-section.rejected .outcome-title {
            color: #d97706;
        }
        .outcome-section.approved .outcome-title {
            color: #047857;
        }
        .outcome-section.cancelled .outcome-title {
            color: #6b7280;
        }
        .outcome-message {
            margin: 0;
            font-size: 16px;
            line-height: 1.6;
            color: #2d2620;
        }
        .comment-box {
            margin: 16px 0 0 0;
            padding: 12px;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            border-left: 3px solid #6b7280;
        }
        .comment-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7a7268;
            margin-bottom: 6px;
        }
        .comment-text {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #2d2620;
            font-style: italic;
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
        .divider {
            margin: 24px 0;
            border: none;
            border-top: 1px solid #e8e0d6;
        }
        .next-steps {
            background-color: #ecf6f6;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .next-steps-title {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
            color: #006e6e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .next-steps-text {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #2d2620;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ strtolower($outcome) }}">
            <h1>
                @if($outcome === 'approved')
                    ✓ Approved
                @elseif($outcome === 'rejected')
                    ✗ Rejected
                @else
                    ⊗ Cancelled
                @endif
            </h1>
        </div>

        <div class="content">
            <p class="greeting">Hello <strong>{{ $recipientName }},</strong></p>

            <div class="outcome-section {{ strtolower($outcome) }}">
                <div class="outcome-title">
                    @if($outcome === 'approved')
                        Document approved
                    @elseif($outcome === 'rejected')
                        Document rejected
                    @else
                        Document cancelled
                    @endif
                </div>
                <p class="outcome-message">
                    @if($outcome === 'approved')
                        {{ $documentLabel }} has been fully reviewed and approved. You can now proceed with the next steps in the procurement process.
                    @elseif($outcome === 'rejected')
                        {{ $documentLabel }} has been rejected and returned for revisions.
                    @else
                        {{ $documentLabel }} has been cancelled and is no longer in the approval workflow.
                    @endif
                </p>

                @if($comment)
                    <div class="comment-box">
                        <div class="comment-label">Note from reviewer</div>
                        <p class="comment-text">"{{ $comment }}"</p>
                    </div>
                @endif
            </div>

            <div class="document-info">
                <div class="document-label">Document</div>
                <p class="document-name">{{ $documentLabel }}</p>
            </div>

            <div style="text-align: center;">
                <a href="{{ $documentUrl }}" class="cta-button">View Document</a>
            </div>

            @if($outcome === 'rejected')
                <div class="next-steps">
                    <div class="next-steps-title">Next Steps</div>
                    <p class="next-steps-text">
                        Please review the feedback above and make the necessary revisions. You can resubmit the document for approval once you've addressed the comments.
                    </p>
                </div>
            @elseif($outcome === 'approved')
                <div class="next-steps">
                    <div class="next-steps-title">What's Next</div>
                    <p class="next-steps-text">
                        The document will now proceed through the remainder of the approval workflow or move to the next stage of the procurement process.
                    </p>
                </div>
            @endif

            <hr class="divider">

            <p style="margin: 0; font-size: 14px; color: #7a7268; line-height: 1.6;">
                If you have any questions or need to take action, you can view the full document details by clicking the button above.
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
