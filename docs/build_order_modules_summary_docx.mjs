import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  Table,
  TableRow,
  TableCell,
  WidthType,
  BorderStyle,
} from 'docx';
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkGfm from 'remark-gfm';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const mdPath = path.join(__dirname, 'Order_Modules_Summary.md');
const outPath = path.join(__dirname, 'Order_Modules_Summary_new.docx');
const fallbackPath = path.join(__dirname, 'Order_Modules_Summary.docx');

const markdown = fs.readFileSync(mdPath, 'utf8');

function inlineRuns(node, marks = {}) {
  if (!node) return [];
  if (node.type === 'text') return [new TextRun({ text: node.value || '', ...marks })];
  if (node.type === 'strong') return (node.children || []).flatMap((c) => inlineRuns(c, { ...marks, bold: true }));
  if (node.type === 'emphasis') return (node.children || []).flatMap((c) => inlineRuns(c, { ...marks, italics: true }));
  if (node.type === 'inlineCode') return [new TextRun({ text: node.value || '', font: 'Consolas', size: 18, ...marks })];
  if (node.type === 'link') {
    const label = (node.children || []).map((c) => c.value || '').join('') || node.url;
    return [new TextRun({ text: label, color: '0563C1', underline: {}, ...marks })];
  }
  if (node.type === 'break') return [new TextRun({ break: 1 })];
  if (node.children) return node.children.flatMap((c) => inlineRuns(c, marks));
  return [];
}

function cellText(node) {
  if (!node) return '';
  if (node.type === 'text') return node.value || '';
  if (node.type === 'inlineCode') return node.value || '';
  if (node.children) return node.children.map(cellText).join('');
  return '';
}

function mdTableToDocx(tableNode) {
  const rows = [];
  for (const row of tableNode.children || []) {
    if (row.type !== 'tableRow') continue;
    const isHeader = rows.length === 0;
    const cells = (row.children || [])
      .filter((c) => c.type === 'tableCell')
      .map(
        (cell) =>
          new TableCell({
            width: { size: 2000, type: WidthType.DXA },
            borders: {
              top: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              bottom: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              left: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
              right: { style: BorderStyle.SINGLE, size: 4, color: '999999' },
            },
            children: [
              new Paragraph({
                children: [new TextRun({ text: cellText(cell), bold: isHeader, size: 18 })],
              }),
            ],
          })
      );
    if (cells.length) rows.push(new TableRow({ children: cells }));
  }
  if (!rows.length) return [];
  return [new Table({ width: { size: 100, type: WidthType.PERCENTAGE }, rows }), new Paragraph({ children: [] })];
}

function headingLevel(depth) {
  if (depth === 1) return HeadingLevel.HEADING_1;
  if (depth === 2) return HeadingLevel.HEADING_2;
  if (depth === 3) return HeadingLevel.HEADING_3;
  return HeadingLevel.HEADING_4;
}

function blockToChildren(node) {
  switch (node.type) {
    case 'heading': {
      const text = (node.children || []).map(cellText).join('');
      const pageBreakBefore = node.depth === 2 && text !== 'Recent Orders';
      return [
        new Paragraph({
          heading: headingLevel(node.depth || 1),
          pageBreakBefore,
          spacing: { before: 240, after: 120 },
          children: [new TextRun({ text, bold: (node.depth || 1) <= 2 })],
        }),
      ];
    }
    case 'paragraph': {
      const runs = (node.children || []).flatMap((c) => inlineRuns(c));
      return [new Paragraph({ spacing: { after: 120 }, children: runs.length ? runs : [new TextRun('')] })];
    }
    case 'code': {
      return String(node.value || '')
        .split(/\r?\n/)
        .map(
          (line) =>
            new Paragraph({
              spacing: { after: 0 },
              children: [new TextRun({ text: line || ' ', font: 'Consolas', size: 16 })],
            })
        );
    }
    case 'list': {
      const items = [];
      (node.children || []).forEach((item, i) => {
        const prefix = node.ordered ? `${(node.start || 1) + i}. ` : '• ';
        const parts = [];
        for (const child of item.children || []) {
          if (child.type === 'paragraph') parts.push(...(child.children || []).flatMap((c) => inlineRuns(c)));
        }
        items.push(
          new Paragraph({
            spacing: { after: 60 },
            indent: { left: 360 },
            children: [new TextRun(prefix), ...(parts.length ? parts : [new TextRun('')])],
          })
        );
      });
      return items;
    }
    case 'table':
      return mdTableToDocx(node);
    case 'thematicBreak':
      return [
        new Paragraph({
          spacing: { before: 120, after: 120 },
          children: [new TextRun({ text: '────────────────────────────────' })],
        }),
      ];
    default:
      return node.children ? node.children.flatMap(blockToChildren) : [];
  }
}

const tree = unified().use(remarkParse).use(remarkGfm).parse(markdown);
const children = (tree.children || []).flatMap(blockToChildren);

const doc = new Document({
  creator: 'Complaint Management',
  title: 'Order Modules — Functional Summary',
  description: 'Recent Orders and Order Booking module summaries',
  sections: [
    {
      properties: { page: { margin: { top: 720, right: 720, bottom: 720, left: 720 } } },
      children,
    },
  ],
});

const buffer = await Packer.toBuffer(doc);
try {
  fs.writeFileSync(outPath, buffer);
  console.log('Wrote', outPath, `(${buffer.length} bytes)`);
} catch (err) {
  if (err && err.code === 'EBUSY') {
    fs.writeFileSync(fallbackPath, buffer);
    console.log('Target locked; wrote', fallbackPath, `(${buffer.length} bytes)`);
  } else {
    throw err;
  }
}
