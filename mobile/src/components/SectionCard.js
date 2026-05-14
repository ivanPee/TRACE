import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

export default function SectionCard({ title, subtitle, children, tone = 'default', icon }) {
  return (
    <View style={[styles.card, tone === 'soft' ? styles.soft : null]}>
      {(title || subtitle) && (
        <View style={styles.header}>
          <View style={styles.titleRow}>
            {icon ? (
              <View style={styles.iconWrap}>
                <FontAwesome5 name={icon} size={14} solid color={colors.accent} />
              </View>
            ) : null}
            {title ? <Text style={styles.title}>{title}</Text> : null}
          </View>
          {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
        </View>
      )}
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.white,
    borderRadius: 22,
    padding: 18,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#ececec',
  },
  soft: {
    backgroundColor: colors.sky,
  },
  header: {
    marginBottom: 12,
  },
  titleRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
  },
  iconWrap: {
    alignItems: 'center',
    backgroundColor: '#edf7ff',
    borderRadius: 10,
    height: 28,
    justifyContent: 'center',
    width: 28,
  },
  title: {
    flex: 1,
    fontSize: 18,
    fontWeight: '800',
    color: colors.ink,
  },
  subtitle: {
    marginTop: 4,
    fontSize: 14,
    lineHeight: 20,
    color: colors.slate,
  },
});
